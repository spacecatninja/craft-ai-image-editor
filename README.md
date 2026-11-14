# AI Image Editor for Craft CMS

Edit and generate image assets in the Craft CMS control panel using natural language, powered by AI.

Open any image asset and tell the editor what you want in plain words — *"make the background white"*, *"remove the coffee cup"*, *"make it look like golden hour"* — then iterate in a chat-style loop until it's right and save the result, either as a new asset or back over the original. Need an image that doesn't exist yet? Describe it and generate one from scratch. No fixed one-click filters, no round-trips to a desktop editor, no leaving Craft.

**Learn more at [craft-ai-image-editor.com](https://craft-ai-image-editor.com/).**

## Requirements

- Craft CMS 6.0+ (version 1.x supports Craft 5, see [UPGRADE.md](UPGRADE.md) when moving to Craft 6)
- PHP 8.5+
- An API key for at least one supported provider — Google Gemini, OpenAI, Black Forest Labs (FLUX), or xAI (Grok). Bring your own key; requests go directly from your server to the provider.

## Installation

To install the plugin, either install it from the Plugin Store, or follow these instructions:

1. Install with Composer via `composer require spacecatninja/ai-image-editor` from your project directory.
2. Install the plugin in the Craft Control Panel under Settings → Plugins, or from the command line via `php artisan craft:plugin:install ai-image-editor`.
3. For the plugin work, you need to [configure at least one driver](#configuration) and verify the connection with `php artisan ai-image-editor:test-connection`. But first, read on!

## Highlights

- **Natural-language editing, inside the control panel.** Free-form instructions in a chat-style composer — not a fixed set of one-click filters. Editors never leave Craft, and never round-trip through a desktop image editor.
- **Generate images from scratch, too.** Beyond editing existing assets, describe an image in words and the plugin creates it as a new asset in the folder you choose.
- **Iterate cheaply, finalize sharp.** Turns run as fast, low-resolution **drafts** while you refine the look; the full-resolution version is rendered only once you accept, with a side-by-side draft/final comparison so nothing changes behind your back.
- **Bring your own AI provider.** Four drivers ship in the box — **Gemini**, **OpenAI**, **FLUX**, and **Grok** — chosen in your config file, a one-line change to swap. Requests go directly from your server to the provider with your own API key, and other plugins can register more drivers.
- **Built to stay faithful.** A "Precise edits" mode, multi-turn provider chaining, and automatic aspect-ratio pinning combine to change only what you asked for and stop quality from drifting across successive edits.
- **Feels like part of Craft.** Follows the native image editor's layout and its two save actions — replace the original in place, or save as a new sibling asset — respects your volume permissions, auto-detects focal points, and names generated files from an AI description of what's in them.
- **Ready for content-disclosure rules.** An optional field flags every AI-edited or -generated asset so you can meet AI-labeling regulations, and it pairs with [Imager X](https://imager-x.spacecat.ninja/) when you need a watermark burned into the pixels.
- **Cloud- and cluster-friendly.** Works unchanged on Craft Cloud and on load-balanced or ephemeral filesystems — working images live on Craft's temporary asset upload disk, never a hardcoded local path.
- **Configured as code.** No settings screen to click through: everything lives in `config/craft/ai-image-editor.php`, so it's versioned, and environment-specific through `env()`.
- **Extensible.** Events before and after every edit and save let you observe, adjust, or veto them, and you can register custom drivers for other providers.

## How it works

- Edits during the chat loop are performed at the configured **working resolution** (default 1K). These are the **draft** versions: cheap and fast, good enough to judge whether an edit is right.
- When you accept the result, the last accepted draft is regenerated once at the **final resolution** (default 2K) before it's saved as an asset. (Drivers with a single resolution tier, like OpenAI, skip this and save the draft directly.)
- **Note:** the finalize step is a generative regeneration, not a pixel-preserving upscale. The editor shows a draft/final comparison so you can catch drift, and lets you save the draft instead if the final version isn't faithful.
- Like the native image editor, there are two save actions: **Save** replaces the original asset's file (the asset and its relations stay intact, requires the "Replace files" volume permission), and **Save as a new asset** creates a sibling asset in the same folder (requires the "Save assets" volume permission).
- Intermediate results are never Craft assets. They live on Craft's temporary asset upload disk and are purged when a session is finalized, discarded, or abandoned (see [Craft Cloud and ephemeral filesystems](#craft-cloud-and-ephemeral-filesystems)).
- In both cases the file is named `{original-basename}-edit-{timestamp}.{ext}`. If the original already has an edit suffix, it's replaced rather than stacked, so re-editing doesn't compound it.
- As part of saving, the image's focal point is detected and set on the asset before it's created, so transforms anchor to the subject from the first render. Assets that already have a focal point keep it. Disable with `'autoFocalPoint' => false`.
- If you set `aiGeneratedField` to the handle of a lightswitch field on your asset volumes, that field is switched on for every asset the plugin edits or generates (including replaced originals), giving you a durable, queryable flag to mark AI-touched images. See [Marking AI-generated content](#marking-ai-generated-content).

### Opening the editor

Open the editor with **Edit with AI**: an action on asset indexes (select one image), an item in an image asset's action menu, and a button in the asset editor's footer. These show for users with the "Edit images with AI" permission, on raster images in volumes they can save assets to.

### Generating new images

Besides editing existing assets, the plugin can create images from scratch. A generation session opens the same editor without a source image: describe what you want, iterate in drafts, and accept to save. Generated assets land in the folder that was selected in the index, named after an AI description of the image (e.g. `golden-retriever-on-a-beach.png`), falling back to the first prompt. Since there is no original, only "Save as a new asset" is offered.

Craft 6 doesn't yet let plugins add buttons to the asset index toolbar or the asset selection modal, so the **Generate** button from the Craft 5 version isn't there. Until it is, open a generation session from your own control panel code, with the ID of the folder to save into:

```js
window.dispatchEvent(new CustomEvent('ai-image-editor:generate', {detail: {folderId: 1}}));
```

### Reducing content drift

Generative editing can change more than you asked for. The plugin does several things to keep edits faithful:

- The **"Precise edits" toggle** in the editor (on by default) appends content-preservation instructions to every prompt, telling the model to change only what was requested. On the GPT Image 2.6 models it also sets the API's `complexity` to `low`, which OpenAI recommends for focused edits. Turn it off for deliberately creative edits, or tune the wording with the `preserveInstructions` setting.
- On providers that support it (currently Gemini, via interaction chaining), consecutive edits reuse the provider's **multi-turn state** instead of re-uploading the previous result, so quality loss doesn't compound across turns. The stateless drivers (OpenAI, FLUX, Grok) re-send the current working image each turn instead.
- The output **aspect ratio is pinned** to the source image's, preventing recomposition. For deliberate ratio changes ("crop this to a square"), pick a ratio in the editor's aspect ratio dropdown instead of relying on the prompt, the default "Match original" follows the current working image.

If simple edits still drift, you can change to more advanced models or adjust the fidelity knobs per driver (Gemini's `'thinkingLevel'`, OpenAI's `quality` and `complexity`, FLUX's `safetyTolerance`, to name a few).

## Drivers

Four drivers ship with the plugin, selected with the `driver` setting:

- **`gemini`**: Google's Gemini image models. Model aliases `nano-banana-pro` (gemini-3-pro-image), `nano-banana-2.1` (gemini-nano-banana-2.1) and `nano-banana-2` (gemini-3.1-flash-image), resolution tiers up to 4K, 10 aspect ratios, and multi-turn interaction chaining for low-drift iteration. Analysis runs on `gemini-3.8-flash`.
- **`openai`**: OpenAI's GPT Image models (`gpt-image-2.6-flare`, `gpt-image-2.6-sunburst`, `gpt-image-2.5-flare`, `gpt-image-2.5-sunburst`, `gpt-image-2`). The 2.5 and 2.6 models reach 2K, so `1K` and `2K` tiers are exposed for them and accepting a result regenerates at the final tier; `gpt-image-2` tops out around 1.5K, where a single `1K` tier is exposed and accepting saves directly. Flare is the fast default, Sunburst trades speed for editing precision. Three aspect ratios (1:1, 3:2, 2:3). The 2.6 models also take a `complexity` setting. Turns are stateless (no chaining). The retired `gpt-image-1`, `gpt-image-1.5` and `gpt-image-1-mini` models are swapped for `gpt-image-2.6-flare`.
- **`flux`**: Black Forest Labs' FLUX 3 Image models (`flux-3-image`, `flux-3-image-klein`) and FLUX.2 models (`flux-2-max`, `flux-2-pro`, `flux-2-flex`, `flux-2-klein-9b`), which do both editing and text-to-image generation at up to ~4MP, so `1K` and `2K` tiers are exposed. Stateless turns. The API is asynchronous (the plugin submits, polls, and downloads the result behind the usual spinner). FLUX has **no vision model**, so set `analysisDriver` to `gemini` or `openai` if you want focal point detection and descriptive filenames.
- **`grok`**: xAI's Grok Imagine models (`grok-imagine-image-2.0`, `grok-imagine-image`), doing both editing and text-to-image generation at `1K` and `2K`. Grok Imagine 2.0 also takes a `quality` setting. Stateless turns. Grok has its own vision model (`grok-4.7`), so it handles the analysis tasks in-provider, no `analysisDriver` needed. The output format is chosen by the API, so no format tier is offered. The retired `grok-imagine-image-quality` model is swapped for `grok-imagine-image-2.0`.

The `gemini`, `openai` and `grok` drivers also handle the analysis tasks (focal point detection, descriptive filenames) with their provider's vision models, configurable via each driver's `analysisModel` key. `flux` has no vision model, so it relies on `analysisDriver` (below).

Additional drivers can be registered by other plugins through the `EditDrivers` registry (see [Extending](#extending)). They read their own configuration from `driverConfig[handle]`, so a third-party driver gets typed-free settings without touching the core config, and the simplest ones can extend `SpaceCatNinja\AiImageEditor\Drivers\BaseEditDriver` to inherit config reading, credentials, prompt building, and the analysis wrappers.

## Configuration

There is no control panel settings screen. All configuration is done in `config/craft/ai-image-editor.php`, a plain Laravel config file. Use `env()` for secrets and environment-specific values:

```php
<?php

return [
    'driver' => 'gemini',
    'driverConfig' => [
        'gemini' => [
            'apiKey' => env('GEMINI_API_KEY'),
        ],
        'openai' => [
            'apiKey' => env('OPENAI_API_KEY'),
        ],
    ],
];
```

The plugin ships a fully commented example in `config/ai-image-editor.php`.

### Root settings

The root of the config holds the driver selectors and cross-cutting settings; each driver's own settings live in its `driverConfig` block (below).

| Setting | Default | Description |
|---------|---------|-------------|
| `driver` | _(required)_ | The edit driver to use: `gemini`, `openai`, `flux` or `grok`. No default — each driver needs its own API key, so you must pick one. Additional drivers can be registered by other plugins/modules. |
| `analysisDriver` | `null` | The driver used for analysis tasks (focal point detection, descriptive filenames), when it should differ from the edit driver. Null uses the edit driver. Set this to give an edit driver without a vision model (e.g. the FLUX driver) working analysis via a provider that has one. |
| `workingResolution` | `'1K'` | Resolution tier used during the chat loop. Kept low to make iterating cheap and fast. Overridable per driver in `driverConfig`. |
| `finalResolution` | `'2K'` | The default resolution tier for the final result, switchable per session in the editor. Overridable per driver in `driverConfig`. |
| `maxResolution` | `null` | Caps the resolution tiers offered in the editor (values `512`, `1K`, `2K`, `4K`), e.g. `'2K'` to hide a driver's `4K` option. Null exposes every tier the driver supports. Overridable per driver in `driverConfig`. |
| `finalizePrompt` | `null` | Overrides the prompt used for the finalize regeneration step. |
| `preserveInstructions` | `null` | Overrides the content-preservation instructions appended to prompts when the "Precise edits" toggle is on. Null uses the driver's default text. |
| `autoFocalPoint` | `true` | Automatically detect and set a focal point on saved results that don't have one, as part of the save. |
| `descriptiveFilenames` | `true` | Name generated images after an AI description of the image. When disabled (or when analysis fails), the first prompt is used instead. |
| `aiGeneratedField` | `null` | Handle of a boolean/lightswitch field on your asset volumes that is switched on when an asset is edited or generated with AI. Use it to mark AI-edited images (e.g. for disclosure requirements); how you surface that is up to you. |
| `presets` | `[]` | Quick-action chips shown in the editor composer for common edits. An array of entries, each with a `label`, a `prompt`, and an optional `precise` boolean (see below). |
| `requestTimeout` | `120` | Timeout in seconds for provider API requests. |
| `purgeSessionsAfterHours` | `48` | Abandoned sessions and their temp files are purged after this many hours. |
| `maxRequestsPerMinute` | `20` | Per-user cap on edit, generate, and finalize requests, a safety limit against runaway retry loops running up provider costs. Set to `0` to disable the throttle. |

#### Quick actions (`presets`)

Presets are one-click chips shown above the composer for common edits. Each entry needs a `label` and a `prompt`; `precise` is optional and overrides the "Precise edits" toggle for that action (use `true` for faithful edits like background removal, leave it off for creative restyles). Clicking a chip runs a normal edit turn with that prompt, and leaves any text you've typed untouched.

```php
'presets' => [
    ['label' => 'Remove background', 'prompt' => 'Remove the background, leaving a clean transparent or white backdrop.', 'precise' => true],
    ['label' => 'Enhance', 'prompt' => 'Improve the lighting, sharpness and color balance.', 'precise' => true],
    ['label' => 'Black & white', 'prompt' => 'Convert to a rich black and white photograph.'],
],
```

### Per-driver settings (`driverConfig`)

`driverConfig` is keyed by driver handle. Only the keys you set override that driver's defaults, so a block with just `apiKey` is enough to get started. Any driver, bundled or third-party, reads its config from here. The overridable [root settings](#root-settings) can also be set here to override their global value for a single driver (see the last three rows).

| Key | Drivers | Default | Description |
|-----|---------|---------|-------------|
| `apiKey` | all | `null` | The provider's API key. Reference an environment variable, never commit the key itself. |
| `defaultModel` | all | driver's first model | The default model for new sessions, switchable per session in the editor. A model the provider has retired is swapped for its replacement, and a deprecation warning is logged. |
| `analysisModel` | all | driver's default | The model used for analysis tasks. Must be a model this driver offers. |
| `quality` | `openai` | `'auto'` | The OpenAI quality level, its main fidelity/cost knob: `auto`, `low`, `medium` or `high`, plus `xhigh` and `max` on the 2.5 and 2.6 models (clamped to `high` on older ones). |
| `complexity` | `openai` | `null` | How much the GPT Image 2.6 models plan before rendering: `low`, `medium` or `high`. Lower suits focused edits, higher helps with dense text and complex layouts but can introduce unrequested changes. Null uses `low` for edits with "Precise edits" on, and the API default (`high`) otherwise. Ignored by the older models. |
| `quality` | `grok` | `null` | The Grok Imagine 2.0 quality level: `auto`, `low` or `medium`. Ignored by the older Grok models, which have no such parameter. |
| `thinkingLevel` | `gemini` | `null` | Reasoning depth: `minimal` or `high`, plus `medium` on Nano Banana 2.1. Higher improves instruction adherence on complex edits, at the cost of latency. |
| `outputFormat` | openai, flux | source image's format | The output image format, selectable per session in the editor (defaulting to the source image's format). This config key sets the fallback when no format is selected. Supported: `png`, `jpeg`, `webp` (FLUX 3 Image Klein: `png` or `jpeg`; FLUX 3 Image and Grok: not supported, format is API-chosen; Gemini: `jpeg` only, so no picker is shown). |
| `safetyTolerance` | `flux` | `2` | Content-moderation strictness, `0` (strict) to `5` (permissive) on FLUX.2, or up to `7` on FLUX 3. BFL decides the highest level your account may use, and rejects requests above it. |
| `grounding` | `flux` | `false` | Lets FLUX 3 Image run web and image searches to get real things right, at the cost of latency. Off by default, since it sends the prompt to a search. Ignored by the other FLUX models. |
| `workingResolution` | all | root `workingResolution` (`'1K'`) | Overrides the root [`workingResolution`](#root-settings) for this driver — the resolution tier used during the chat loop. |
| `finalResolution` | all | root `finalResolution` (`'2K'`) | Overrides the root [`finalResolution`](#root-settings) for this driver — the default tier for the final result. |
| `maxResolution` | all | root `maxResolution` (`null`) | Overrides the root [`maxResolution`](#root-settings) for this driver — caps the resolution tiers offered in the editor (`512`, `1K`, `2K`, `4K`). |

After configuring, verify the setup from the command line:

```bash
php artisan ai-image-editor:test-connection
```

### Splitting analysis from editing

Analysis (focal point detection, descriptive filenames) needs a vision-language model, which not every image-editing provider has. By default it runs on the same provider as editing, but the `analysisDriver` setting decouples them: set `driver` to an edit-only provider and `analysisDriver` to one with a vision model, and analysis still works. For example, the FLUX driver has no vision model, so pair it with Gemini:

```php
'driver' => 'flux',
'analysisDriver' => 'gemini',
'driverConfig' => [
    'flux' => ['apiKey' => env('FLUX_API_KEY')],
    'gemini' => [
        'apiKey' => env('GEMINI_API_KEY'),
        'analysisModel' => 'gemini-3.8-flash',
    ],
],
```

When `analysisDriver` names a different provider, remember it needs its own API key in its `driverConfig` block. `php artisan ai-image-editor:test-connection` verifies both providers.

## Marking AI-generated content

A growing number of jurisdictions require content that was generated or manipulated by AI to be disclosed as such. The EU AI Act's transparency obligations (Article 50), China's labeling rules for AI-generated content, and a wave of US state laws (such as California's AI Transparency Act) are all examples, with more on the way. What must be marked, how, and from when varies by jurisdiction and by how the image is used — so treat the field below as a building block, not legal advice. Whether and how you need to disclose is your responsibility to determine, ideally with counsel; the plugin does not make your site compliant on its own.

The `aiGeneratedField` setting gives you that building block. Point it at the handle of a lightswitch (boolean) field in your asset volumes' field layouts, and the plugin switches it on for every asset it edits or generates — including originals replaced in place via **Save**. Because it's an ordinary Craft field, the flag is durable, queryable, and yours to act on however the rules require, for example:

- **Show a visible disclosure** next to the image on the front end — a caption, badge, or "Generated with AI" label — by checking the field in your templates.
- **Filter or report** on AI-touched assets, e.g. an element index source or a query like `craft.assets.isAiGenerated(true)` for audits (using your field's handle).
- **Feed provenance/metadata** pipelines your own code drives on save (IPTC fields, C2PA manifests, `alt`-text notes, and so on).

The plugin only ever flips the field **on**; how it's surfaced, exported, enforced, or combined with other signals is entirely up to you.

**Burning in a visible watermark.** When you need a watermark composited into the pixels rather than a separate on-page label, [Imager X](https://imager-x.spacecat.ninja/) can add one as part of a transform via its `watermark` parameter — a watermark `image` plus `width`/`height`, and optional `position`, `opacity`, and `blendMode`. Drive it off the `aiGeneratedField` flag so only AI-generated assets are marked (assuming the field's handle is `isAiGenerated`):

```twig
{% set transform = { width: 1200 } %}
{% if asset.isAiGenerated %}
    {% set transform = transform | merge({ watermark: {
        image: aiBadge, width: 160, height: 160,
        position: { bottom: 20, right: 20 }, opacity: 70,
    } }) %}
{% endif %}
{{ craft.imagerx.transformImage(asset, transform).url }}
```

Imager X watermarking runs on the local GD/Imagick transformer.

## Craft Cloud and ephemeral filesystems

An edit session spans several requests (each turn, finalize, and save is a separate request), so the working images have to survive between them. The plugin keeps them on Craft's temporary asset upload disk (the `tempAssetUploadDisk` general config setting). Point that at shared or object storage on **Craft Cloud** or a load-balanced setup, and the files are shared across instances. Nothing is written to a hardcoded local path, and this storage is **not** an asset volume filesystem, so a volume re-index never picks up the transient files.

These files are genuinely temporary: they're removed as soon as a session is finalized, discarded, or purged (`purgeSessionsAfterHours`, default 48). If the platform's temp storage is reclaimed sooner, the worst case is a "start a new session" message on the next turn — no data is lost, since finished results are saved as normal Craft assets.

## Privacy

Images and prompts are sent to the AI provider(s) you configure — the edit driver, plus the analysis driver when you've set a separate one — for processing. Make sure this is acceptable for your project's data-handling obligations before enabling the plugin. The editor shows a note naming the provider(s) at the point of use.

## Extending

### Edit drivers

Additional providers can register edit drivers with the `EditDrivers` registry, from a plugin or module's `boot()` method:

```php
use SpaceCatNinja\AiImageEditor\Drivers\EditDrivers;

public function boot(EditDrivers $drivers): void
{
    $drivers->register('my-driver', MyEditDriver::class);
}
```

Drivers implement `SpaceCatNinja\AiImageEditor\Drivers\EditDriverInterface`, and are created through the container, so their constructors can take dependencies. Registering an existing handle replaces that driver.

### Lifecycle events

Four Laravel events let you observe, adjust, or veto edits and saves. All are in the `SpaceCatNinja\AiImageEditor\Events` namespace:

| Event | When | Properties |
|-------|------|------------|
| `EditingImage` | Before each turn is sent to the driver | `session`, `request` (mutable), `isValid` |
| `ImageEdited` | After a successful turn | `session`, `request`, `result` |
| `SavingImage` | Before the result is saved as an asset | `session`, `asset`, `replace`, `isValid` |
| `ImageSaved` | After the result is saved | `session`, `asset`, `replace` |

Set `isValid = false` in an `EditingImage` or `SavingImage` listener to cancel.

```php
use Illuminate\Support\Facades\Event;
use SpaceCatNinja\AiImageEditor\Events\EditingImage;
use SpaceCatNinja\AiImageEditor\Events\SavingImage;

// Tweak or veto a prompt before it runs.
Event::listen(function(EditingImage $event) {
    $event->request->prompt .= ' Keep the brand colors intact.';
    // $event->isValid = false; // cancel the turn
});

// Set a field on every AI-saved asset (e.g. alt text).
Event::listen(function(SavingImage $event) {
    $event->asset->setFieldValue('altText', 'Edited with AI');
});
```

### Opening the editor from your own code

The editor script is loaded on every control panel page. It opens on two window events: `ai-image-editor:edit` with the asset ID as `assetId` in the event detail, and `ai-image-editor:generate` with the target folder ID as `folderId`. After a save, it dispatches `ai-image-editor:saved` with the saved asset in the detail.

```js
window.dispatchEvent(new CustomEvent('ai-image-editor:edit', {detail: {assetId: 123}}));
```

### Permissions

The plugin registers two user permissions under an "AI Image Editor" group, assignable per user group:

- `ai-image-editor:edit` — open the editor on an existing image and run edits.
- `ai-image-editor:generate` — generate brand new images.

These gate the editor's entry points and endpoints; the volume permissions (`saveAssets`, `replaceFiles`) still gate the actual save. Reference the handles from `AiImageEditor::PERMISSION_EDIT` / `PERMISSION_GENERATE`.

### Console commands

- `php artisan ai-image-editor:test-connection` verifies the configured provider(s).
- `php artisan ai-image-editor:sessions:purge` purges abandoned sessions and their temporary files, optionally with `--hours=<n>`. The plugin also queues a purge job from control panel requests, at most every six hours.

## Support

Found a bug or have a feature request? Open an issue on the [issue tracker](https://github.com/spacecatninja/craft-ai-image-editor/issues). For anything else, email [hi@spacecat.ninja](mailto:hi@spacecat.ninja).

## License

This is commercial software. See [LICENSE.md](LICENSE.md). A license can be purchased through the Craft Plugin Store.

---

[craft-ai-image-editor.com](https://craft-ai-image-editor.com/) — brought to you by [SPACECATNINJA](https://www.spacecat.ninja)
