<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Enums\FileKind;
use CraftCms\Cms\Element\Actions\ClientAction;
use CraftCms\Cms\Element\Enums\ElementActionContext;
use CraftCms\Cms\Element\Events\ElementActionMenuDescriptorsResolving;
use CraftCms\Cms\Element\Events\ElementActionMenuItemsResolving;
use CraftCms\Cms\Element\Events\ElementActionsResolving;
use CraftCms\Cms\Element\Events\ElementAdditionalButtonDescriptorsResolving;
use CraftCms\Cms\Plugin\Plugin;
use CraftCms\Cms\Plugin\PluginSettings;
use CraftCms\Cms\User\Data\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use SpaceCatNinja\AiImageEditor\Console\Commands\PurgeSessionsCommand;
use SpaceCatNinja\AiImageEditor\Console\Commands\TestConnectionCommand;
use SpaceCatNinja\AiImageEditor\Jobs\PurgeStaleSessions;
use SpaceCatNinja\AiImageEditor\Services\SessionsService;
use Throwable;

use function CraftCms\Cms\t;

/**
 * AI Image Editor lets editors modify existing assets through free-form,
 * natural-language instructions in a chat-style loop, and save the accepted
 * result as a new asset.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class AiImageEditor extends Plugin
{
    // Const Properties
    // =========================================================================

    /**
     * @var string The permission required to edit existing images with AI.
     * @since 2.0.0
     */
    public const PERMISSION_EDIT = 'ai-image-editor:edit';

    /**
     * @var string The permission required to generate new images with AI.
     * @since 2.0.0
     */
    public const PERMISSION_GENERATE = 'ai-image-editor:generate';

    /**
     * @var string The window event that opens the editor. Its detail carries the
     * asset ID as `elementIds` (from element actions) or `assetId`.
     * @since 2.0.0
     */
    public const EDIT_EVENT = 'ai-image-editor:edit';

    /**
     * @var string The URL hash that opens the editor for an asset, for the
     * Craft menus that can only link. `{id}` is replaced with the asset ID.
     */
    private const EDIT_HASH = '#ai-image-editor:edit:{id}';

    /**
     * @var string The URL hash that opens the editor from the asset's own edit
     * screen, which the editor then follows after saving.
     */
    private const EDIT_SCREEN_HASH = '#ai-image-editor:edit-screen:{id}';

    /**
     * @var string Cache key that throttles dispatching the stale session purge job.
     */
    private const PURGE_SCHEDULED_CACHE_KEY = 'ai-image-editor:purge-scheduled';

    // Public Properties
    // =========================================================================

    public string $schemaVersion = '2.0.0';

    protected array $commands = [
        PurgeSessionsCommand::class,
        TestConnectionCommand::class,
    ];

    protected array $scripts = [
        __DIR__ . '/../resources/dist/editor.js' => 'editor.js',
    ];

    protected array $styles = [
        __DIR__ . '/../resources/dist/editor.css' => 'editor.css',
    ];

    // Public Methods
    // =========================================================================

    public function register(): void
    {
        parent::register();

        // Bound here rather than in boot(), because Craft loads the plugin's routes before boot() runs.
        $this->app->singleton(Settings::class, fn() => $this->getSettings());
    }

    public function boot(): void
    {
        $this->registerEditorEntryPoints();
        $this->schedulePurgeJob();
    }

    // Protected Methods
    // =========================================================================

    protected static function createSettings(): ?PluginSettings
    {
        return new Settings();
    }

    /**
     * The plugin's user permissions: one for editing existing images with AI,
     * one for generating new ones. These gate the editor in addition to the
     * volume permissions that gate the actual save.
     *
     * @return Permission[]
     */
    protected function getPermissions(): array
    {
        return [
            new Permission(self::PERMISSION_EDIT, t('Edit images with AI', category: 'ai-image-editor')),
            new Permission(self::PERMISSION_GENERATE, t('Generate images with AI', category: 'ai-image-editor')),
        ];
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers the control panel entry points for opening the editor: an
     * element action on asset indexes, an action menu item on assets, and a
     * button in the asset editor's footer.
     *
     * The editor itself is `resources/dist/editor.js`, loaded on every control
     * panel page, which opens on the `ai-image-editor:edit` window event. The
     * action menus of the full-page asset editor and of element chips can only
     * run Craft's own behaviors, not dispatch events, so those link to a URL
     * hash the editor listens for instead. Switch them to an event behavior,
     * and drop the hash, once Craft supports one:
     * https://github.com/craftcms/cms/issues/20688
     */
    private function registerEditorEntryPoints(): void
    {
        Event::listen(static function(ElementActionsResolving $event): void {
            if (!is_a($event->elementType, Asset::class, true) || !Gate::check(self::PERMISSION_EDIT)) {
                return;
            }

            $event->actions[] = new ClientAction(
                key: 'ai-image-editor:edit',
                label: t('Edit with AI', category: 'ai-image-editor'),
                event: self::EDIT_EVENT,
                bulk: false,
                selectionAttribute: 'imageEditable',
            );
        });

        // Server-rendered menus (slideouts, the legacy element editor) dispatch window events.
        Event::listen(static function(ElementActionMenuItemsResolving $event): void {
            if (!self::canEditAsset($event->element)) {
                return;
            }

            $event->items[] = [
                'id' => sprintf('action-ai-image-edit-%s', mt_rand()),
                'icon' => 'wand-magic-sparkles',
                'label' => t('Edit with AI', category: 'ai-image-editor'),
                'action' => [
                    'type' => 'event',
                    'name' => self::EDIT_EVENT,
                    'detail' => ['assetId' => $event->element->id],
                ],
            ];
        });

        // The full-page asset editor and element chips only run behaviors, so these link to the editor's URL hash.
        Event::listen(static function(ElementActionMenuDescriptorsResolving $event): void {
            if (!self::canEditAsset($event->element)) {
                return;
            }

            $event->items[] = [
                'label' => t('Edit with AI', category: 'ai-image-editor'),
                'icon' => 'wand-magic-sparkles',
                'behavior' => [
                    'type' => 'link',
                    'href' => self::editHash($event->element, $event->context === ElementActionContext::Editor),
                ],
            ];
        });

        // Next to Craft's own buttons in the asset editor's footer, like the Craft 5 button beside "Edit Image".
        Event::listen(static function(ElementAdditionalButtonDescriptorsResolving $event): void {
            if (!self::canEditAsset($event->element)) {
                return;
            }

            $event->items[] = [
                'label' => t('Edit with AI', category: 'ai-image-editor'),
                'icon' => 'wand-magic-sparkles',
                'behavior' => [
                    'type' => 'link',
                    'href' => self::editHash($event->element, true),
                ],
            ];
        });
    }

    /**
     * Whether the current user may open the editor for an element: an existing
     * raster image asset, with the AI edit permission, and the ability to save
     * the result as an asset in its volume.
     */
    private static function canEditAsset(mixed $element): bool
    {
        if (!$element instanceof Asset || !$element->id || $element->kind !== FileKind::Image->value) {
            return false;
        }

        if (\in_array(strtolower($element->getExtension()), SessionsService::UNSUPPORTED_EXTENSIONS, true)) {
            return false;
        }

        try {
            $volume = $element->getVolume();
        } catch (Throwable) {
            return false;
        }

        return Gate::check(self::PERMISSION_EDIT) && Gate::check("saveAssets:{$volume->uid}");
    }

    private static function editHash(Asset $asset, bool $isEditScreen): string
    {
        return str_replace('{id}', (string)$asset->id, $isEditScreen ? self::EDIT_SCREEN_HASH : self::EDIT_HASH);
    }

    /**
     * Dispatches the stale session purge job from control panel requests, at most once every six hours.
     */
    private function schedulePurgeJob(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->app->booted(static function(): void {
            try {
                if (request()->isCpRequest() && Cache::add(self::PURGE_SCHEDULED_CACHE_KEY, true, now()->addHours(6))) {
                    dispatch(new PurgeStaleSessions());
                }
            } catch (Throwable) {
                // Purging is best-effort, and must never break a control panel request.
            }
        });
    }
}
