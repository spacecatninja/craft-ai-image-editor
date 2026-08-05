<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\models;

use craft\base\Model;

/**
 * Plugin settings. There is no control panel settings screen, all configuration
 * is done through `config/ai-image-editor.php`.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string|null The handle of a boolean/lightswitch field on assets
     * that is switched on when an asset is edited or generated with AI. Lets
     * you mark AI-edited images, e.g. for jurisdictions that require
     * disclosure. What you do with the marked field is up to you. Null
     * disables the marking.
     *
     * @since 1.0.0
     */
    public ?string $aiGeneratedField = null;

    /**
     * @var string|null The handle of the driver used for image analysis tasks
     * (focal point detection, descriptive filenames), when it should differ
     * from the edit driver. Null uses the edit driver, so analysis runs on the
     * same provider that did the editing. Set this to run analysis on a
     * provider with a vision model when the edit driver has none, e.g.
     * `'gemini'` analysis behind a FLUX edit driver.
     *
     * @since 1.0.0
     */
    public ?string $analysisDriver = null;

    /**
     * @var bool Whether a focal point is automatically detected and set on
     * saved results that don't have one.
     *
     * @since 1.0.0
     */
    public bool $autoFocalPoint = true;

    /**
     * @var bool Whether filenames for generated images are based on an AI
     * description of the image, instead of the first prompt.
     *
     * @since 1.0.0
     */
    public bool $descriptiveFilenames = true;

    /**
     * @var string The handle of the edit driver to use: `gemini`, `openai`,
     * `flux`, `grok`, or one registered by another plugin. Required, with no
     * default: every driver needs its own credentials, so there's no sensible
     * one to assume.
     *
     * @since 1.0.0
     */
    public string $driver = '';

    /**
     * @var array<string, array<string, mixed>> Per-driver configuration, keyed
     * by driver handle. Each driver reads its own block, e.g. `apiKey`,
     * `defaultModel`, and provider-specific options like `quality` (OpenAI) or
     * `thinkingLevel` (Gemini). This is where any driver, bundled or
     * third-party, stores its settings.
     *
     * @since 1.0.0
     */
    public array $driverConfig = [];

    /**
     * @var string|null The prompt used for the finalize step, where the last
     * accepted working-resolution result is regenerated at the final
     * resolution. If null, the driver's default is used.
     *
     * @since 1.0.0
     */
    public ?string $finalizePrompt = null;

    /**
     * @var string The resolution tier used for the final result when a session
     * is accepted and saved. Overridable per driver via
     * `driverConfig[handle].finalResolution`.
     *
     * @since 1.0.0
     */
    public string $finalResolution = '2K';

    /**
     * @var int The maximum number of edit/generate/finalize requests a single
     * user may make per minute, a safety cap against runaway retry loops or
     * scripted clients running up provider costs. Set to `0` to disable the
     * throttle entirely.
     *
     * @since 1.0.0
     */
    public int $maxRequestsPerMinute = 20;

    /**
     * @var string|null Caps the resolution tiers offered in the editor, e.g.
     * `'2K'` to hide a driver's `4K` option. Null exposes every tier the
     * driver supports. Overridable per driver via
     * `driverConfig[handle].maxResolution`.
     *
     * @since 1.0.0
     */
    public ?string $maxResolution = null;

    /**
     * @var array Quick-action presets shown as one-click chips in the editor
     * composer. Each entry should be an array with a `label` and a `prompt`, and
     * may set `precise` to override the "Precise edits" toggle for that action.
     * These come straight from config, so they're validated when read.
     *
     * @since 1.0.0
     */
    public array $presets = [];

    /**
     * @var string|null Overrides the driver's default content-preservation
     * instructions, appended to edit prompts when the "Precise edits" toggle
     * is on. If null, the driver's default text is used.
     *
     * @since 1.0.0
     */
    public ?string $preserveInstructions = null;

    /**
     * @var int The number of hours after which abandoned active sessions and
     * their temporary files are purged.
     *
     * @since 1.0.0
     */
    public int $purgeSessionsAfterHours = 48;

    /**
     * @var int The timeout in seconds for edit requests against the provider API.
     *
     * @since 1.0.0
     */
    public int $requestTimeout = 120;

    /**
     * @var string The resolution tier used for edit turns during the chat loop.
     * Overridable per driver via `driverConfig[handle].workingResolution`.
     *
     * @since 1.0.0
     */
    public string $workingResolution = '1K';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['driver'], 'required'];
        $rules[] = [['autoFocalPoint', 'descriptiveFilenames'], 'boolean'];
        $rules[] = [['requestTimeout'], 'integer', 'min' => 5];
        $rules[] = [['purgeSessionsAfterHours'], 'integer', 'min' => 1];
        $rules[] = [['maxRequestsPerMinute'], 'integer', 'min' => 0];

        return $rules;
    }
}
