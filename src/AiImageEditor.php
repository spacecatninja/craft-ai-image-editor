<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\controllers\ElementsController;
use craft\elements\Asset;
use craft\enums\MenuItemType;
use craft\events\DefineMenuItemsEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\View;

use spacecatninja\aiimageeditor\assetbundles\EditorAsset;
use spacecatninja\aiimageeditor\controllers\SessionsController;
use spacecatninja\aiimageeditor\drivers\FluxEditDriver;
use spacecatninja\aiimageeditor\drivers\GeminiEditDriver;
use spacecatninja\aiimageeditor\drivers\GrokEditDriver;
use spacecatninja\aiimageeditor\drivers\OpenAiEditDriver;
use spacecatninja\aiimageeditor\elementactions\EditWithAi;
use spacecatninja\aiimageeditor\events\RegisterEditDriversEvent;
use spacecatninja\aiimageeditor\jobs\PurgeStaleSessions;
use spacecatninja\aiimageeditor\models\Settings;
use spacecatninja\aiimageeditor\services\DriversService;
use spacecatninja\aiimageeditor\services\ServicesTrait;
use spacecatninja\aiimageeditor\services\SessionsService;

use yii\base\Event;
use yii\base\InvalidConfigException;

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
    // Traits
    // =========================================================================

    use ServicesTrait;

    // Const Properties
    // =========================================================================

    /**
     * @event RegisterEditDriversEvent The event that is triggered when edit drivers are registered.
     * @since 1.0.0
     */
    public const EVENT_REGISTER_EDIT_DRIVERS = 'aiImageEditorRegisterEditDrivers';

    // Static Properties
    // =========================================================================

    /**
     * @var AiImageEditor|null The plugin instance.
     */
    public static ?AiImageEditor $plugin = null;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Registered in every context so the permissions exist and enforce
        // everywhere, not just in the control panel.
        $this->_registerPermissions();

        // Deferred until the app has fully initialized. Driver registration must
        // wait so drivers added by other plugins aren't missed due to load order,
        // and the CP entry points read the current user's permissions, which
        // triggers a user-identity query that must not run before Craft is ready
        // (it warns on Craft Cloud otherwise).
        Craft::$app->onInit(function() {
            $this->_registerEditDrivers();

            if (Craft::$app->getRequest()->getIsCpRequest()) {
                $this->_registerCpEntryPoints();
                $this->_schedulePurgeJob();
            }
        });
    }

    /**
     * Returns the plugin settings, merged with any overrides from `config/ai-image-editor.php`.
     *
     * @return Settings|null
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSettings(): ?Settings
    {
        /** @var Settings|null $settings */
        $settings = parent::getSettings();

        return $settings;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers the built-in edit drivers, and any drivers added through the
     * `EVENT_REGISTER_EDIT_DRIVERS` event.
     */
    private function _registerEditDrivers(): void
    {
        $event = new RegisterEditDriversEvent([
            'drivers' => [
                GeminiEditDriver::HANDLE => GeminiEditDriver::class,
                OpenAiEditDriver::HANDLE => OpenAiEditDriver::class,
                FluxEditDriver::HANDLE => FluxEditDriver::class,
                GrokEditDriver::HANDLE => GrokEditDriver::class,
            ],
        ]);

        $this->trigger(self::EVENT_REGISTER_EDIT_DRIVERS, $event);

        foreach ($event->drivers as $handle => $class) {
            DriversService::registerDriver($handle, $class);
        }
    }

    /**
     * Registers the plugin's user permissions: one for editing existing images
     * with AI, one for generating new ones. These gate the editor in addition
     * to the volume permissions that gate the actual save.
     */
    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('ai-image-editor', 'AI Image Editor'),
                    'permissions' => [
                        SessionsController::PERMISSION_EDIT => [
                            'label' => Craft::t('ai-image-editor', 'Edit images with AI'),
                        ],
                        SessionsController::PERMISSION_GENERATE => [
                            'label' => Craft::t('ai-image-editor', 'Generate images with AI'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Registers the control panel entry points for opening the editor: an
     * element action on asset indexes, and an action menu item on assets,
     * placed alongside Craft's native Edit Image action.
     */
    private function _registerCpEntryPoints(): void
    {
        // Registered CP-wide: the asset index toolbar button must be wired
        // before element indexes initialize, and element action assets are
        // only delivered by ajax after that point.
        if (!Craft::$app->getRequest()->getIsAjax()) {
            $view = Craft::$app->getView();
            $view->registerAssetBundle(EditorAsset::class);

            // Expose the generate permission so the index "Generate" button can
            // gate itself; it's drawn before any session (and thus payload) exists.
            $canGenerate = Craft::$app->getUser()->checkPermission(SessionsController::PERMISSION_GENERATE) ? 'true' : 'false';
            $view->registerJs("Craft.AiImageEditor = Craft.AiImageEditor || {}; Craft.AiImageEditor.canGenerate = {$canGenerate};", View::POS_END);
        }

        Event::on(
            Asset::class,
            Element::EVENT_REGISTER_ACTIONS,
            static function(RegisterElementActionsEvent $event) {
                if (Craft::$app->getUser()->checkPermission(SessionsController::PERMISSION_EDIT)) {
                    $event->actions[] = EditWithAi::class;
                }
            }
        );

        Event::on(
            Asset::class,
            Element::EVENT_DEFINE_ACTION_MENU_ITEMS,
            static function(DefineMenuItemsEvent $event) {
                /** @var Asset $asset */
                $asset = $event->sender;

                if ($asset->kind !== Asset::KIND_IMAGE || !$asset->id) {
                    return;
                }

                if (\in_array(strtolower($asset->getExtension()), SessionsService::UNSUPPORTED_EXTENSIONS, true)) {
                    return;
                }

                try {
                    $volume = $asset->getVolume();
                } catch (InvalidConfigException) {
                    return;
                }

                // Requires both the AI-edit permission and the ability to save
                // the result as an asset in this volume.
                $user = Craft::$app->getUser();

                if (!$user->checkPermission(SessionsController::PERMISSION_EDIT) || !$user->checkPermission("saveAssets:{$volume->uid}")) {
                    return;
                }

                $view = Craft::$app->getView();
                $view->registerAssetBundle(EditorAsset::class);

                $itemId = sprintf('action-ai-image-edit-%s', mt_rand());

                $event->items[] = [
                    'type' => MenuItemType::Button,
                    'id' => $itemId,
                    'icon' => 'wand-magic-sparkles',
                    'label' => Craft::t('ai-image-editor', 'Edit with AI'),
                ];

                // On the asset's own edit screen the editor navigates after
                // saving: reload on replace, go to the new asset otherwise.
                $isEditScreen = Craft::$app->controller instanceof ElementsController
                    && Craft::$app->controller->element instanceof Asset
                    && (int)Craft::$app->controller->element->id === (int)$asset->id;

                $view->registerJsWithVars(fn($id, $assetId, $isEditScreen) => <<<JS
$('#' + $id).on('activate', () => {
  Craft.AiImageEditor.open($assetId, {isEditScreen: $isEditScreen});
});
JS, [
                    $view->namespaceInputId($itemId),
                    $asset->id,
                    $isEditScreen,
                ]);

                // On the asset's own edit screen, also surface the editor as a
                // button inside the preview thumbnail, next to Craft's native
                // "Preview" and "Edit Image" buttons.
                if ($isEditScreen) {
                    $view->registerJsWithVars(fn($assetId) => <<<JS
Craft.AiImageEditor.attachEditScreenButton($assetId);
JS, [$asset->id]);
                }
            }
        );
    }

    /**
     * Pushes a purge job for stale sessions, at most once per throttle window.
     */
    private function _schedulePurgeJob(): void
    {
        $cache = Craft::$app->getCache();
        $cacheKey = 'ai-image-editor:purge-scheduled';

        // A string sentinel, since cache->get() returns false for missing keys.
        if ($cache->get($cacheKey) === 'scheduled') {
            return;
        }

        Craft::$app->getQueue()->push(new PurgeStaleSessions());
        $cache->set($cacheKey, 'scheduled', 21600);
    }
}
