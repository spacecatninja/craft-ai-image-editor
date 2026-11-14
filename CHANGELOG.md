# AI Image Editor Changelog

## 2.0.0-beta.1 - 2026-11-14

> [!IMPORTANT]
> AI Image Editor 2 requires Craft CMS 6 and PHP 8.5, and has breaking changes for plugins and modules that extend it. See [UPGRADE.md](UPGRADE.md).

### Added
- Added the `EditDrivers` registry, which plugins and modules register edit drivers with.
- Added the `EditingImage`, `ImageEdited`, `SavingImage` and `ImageSaved` events.
- Added `window.AiImageEditor.open()` and `window.AiImageEditor.generate()`, and the `ai-image-editor:edit`, `ai-image-editor:generate` and `ai-image-editor:saved` window events, for opening the editor from other control panel code.
- Added an **Edit with AI** button to the asset editor's footer.

### Changed
- AI Image Editor now requires Craft CMS 6 and PHP 8.5, and uses Craft 6's native plugin APIs. It doesn't need the Yii 2 adapter.
- Classes moved to the `SpaceCatNinja\AiImageEditor` namespace, and to Laravel-style folders.
- The config file is now `config/craft/ai-image-editor.php`, a plain Laravel config file. Use `env()` instead of `App::env()`, and a flat array instead of the `'*'` multi-environment format.
- The services are now resolved from the container, instead of being components on the plugin.
- Session and turn records are now Eloquent models.
- The console commands are now Artisan commands (`ai-image-editor:test-connection` and `ai-image-editor:sessions:purge`). The Craft 5 names, like `php craft ai-image-editor/test-connection`, still work.
- `PurgeStaleSessions` is now a Laravel job.
- The editor's endpoints are now control panel routes (`<cpTrigger>/ai-image-editor/sessions/<action>`), instead of action URLs.
- The editor is now a dependency-free script with a native `<dialog>`, loaded on every control panel page, instead of a Garnish modal in an asset bundle.
- The **Edit with AI** element action is now a Craft 6 client action.
- The editor now shows the original, the drafts and the final version at the same size, the size of a draft, instead of showing a large original bigger than its drafts.
- Session working images are now stored on Laravel's temporary asset upload disk (`tempAssetUploadDisk`), instead of Craft's `Temp` filesystem.
- The per-user request throttle now uses Laravel's rate limiter.
- The permission constants moved from `SessionsController` to `AiImageEditor`.

### Removed
- Removed the **Generate** button from asset indexes and asset selection modals, since Craft 6 has no way for plugins to add it yet ([craftcms/cms#20689](https://github.com/craftcms/cms/issues/20689)). Generation sessions can still be opened from code.
- Removed the `AiImageEditor::EVENT_REGISTER_EDIT_DRIVERS` event and `RegisterEditDriversEvent`, and `DriversService::registerDriver()` and `getRegisteredDrivers()`.
- Removed the `SessionsService::EVENT_BEFORE_EDIT`, `SessionsService::EVENT_AFTER_EDIT`, `FinalizeService::EVENT_BEFORE_SAVE` and `FinalizeService::EVENT_AFTER_SAVE` events, and `EditEvent` and `SaveEvent`.
- Removed `AiImageEditor::$plugin`, and the `getDrivers()`, `getSessions()` and `getFinalize()` service getters.
- Removed the `EditWithAi` element action class and the `EditorAsset` asset bundle.
- Removed `Craft.AiImageEditor.attachGenerateButton()` and `attachEditScreenButton()`.

### Fixed
- Fixed the "Replace the original image?" confirmation, which wasn't translatable.

## 1.2.0 - 2026-11-14

### Added
- Added the GPT Image 2.6 models `gpt-image-2.6-flare` and `gpt-image-2.6-sunburst`, with the same 2K tier and `xhigh`/`max` quality levels as 2.5
- Added a `complexity` setting for the GPT Image 2.6 models. When it's not set, edits with "Precise edits" on use `low` complexity
- Added the `nano-banana-2.1` model alias (`gemini-nano-banana-2.1`), with resolution tiers from 512 to 4K and thinking level support
- Added the FLUX 3 Image models `flux-3-image` and `flux-3-image-klein`, and a `grounding` setting for `flux-3-image` (off by default)

### Changed
- Changed the default OpenAI model to `gpt-image-2.6-flare`, and the default FLUX model to `flux-3-image`. Set `driverConfig.<handle>.defaultModel` to keep the old ones
- Changed the OpenAI analysis model to `gpt-6.1-terra`
- Changed the retired `gpt-image-1`, `gpt-image-1.5` and `gpt-image-1-mini` models (shut down by OpenAI on December 1, 2026) to be swapped for `gpt-image-2.6-flare`, with a deprecation warning logged. "Precise edits" no longer sets `input_fidelity`, since no remaining model accepts it
- Changed the retired `grok-imagine-image-quality` model (retired by xAI on November 2, 2026) to be swapped for `grok-imagine-image-2.0`, with a deprecation warning logged

## 1.1.0 - 2026-09-28

### Added
- Added the GPT Image 2.5 models `gpt-image-2.5-flare` and `gpt-image-2.5-sunburst`, which reach 2K and support the `xhigh` and `max` quality levels
- Added the `grok-imagine-image-2.0` model, and a `quality` setting for it
- Added the `flux-2-max` and `flux-2-klein-9b` models

### Changed
- Changed the default models to `gpt-image-2.5-flare`, `grok-imagine-image-2.0` and `flux-2-max`. Grok and FLUX cost more per image than the previous defaults, so set `driverConfig.<handle>.defaultModel` to keep the old ones
- Changed the OpenAI driver to expose a `2K` resolution tier on the 2.5 models, where accepting a result now regenerates at the final resolution
- Changed the analysis models to `gemini-3.8-flash`, `gpt-5.4-mini` and `grok-4.7`

## 1.0.0 - 2026-08-01

### Added
- Initial public release
