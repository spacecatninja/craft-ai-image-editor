<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Copy this file to your project's `config/craft/` folder as `ai-image-editor.php`.
 *
 * Craft 6 loads it as plain Laravel config: return a flat array, and use env()
 * for environment variables and per-environment values, like
 * env('APP_ENV') === 'production'. Config files load before Laravel knows the
 * environment, so app()->environment() can't be used here.
 */

return [
    // The edit driver to use, and (optionally) a separate driver for
    // analysis tasks (focal point detection, descriptive filenames).
    'driver' => 'gemini', // required, no default: 'gemini', 'openai', 'flux' or 'grok'
    //'analysisDriver' => null, // e.g. 'gemini' to run analysis on a provider with a vision model

    // Per-driver config, keyed by driver handle. Each driver reads its own
    // block; only the keys you set override the driver's defaults.
    //
    // `workingResolution`, `finalResolution` and `maxResolution` can be set
    // in any block to override the cross-cutting values (below) per driver.
    // `maxResolution` caps the tiers offered in the editor.
    // `outputFormat` is the fallback for the editor's format picker, which
    // otherwise defaults to the source image's format.
    'driverConfig' => [
        'gemini' => [
            'apiKey' => env('GEMINI_API_KEY'),
            //'defaultModel' => 'nano-banana-pro', // or 'nano-banana-2.1', 'nano-banana-2', or a raw Gemini image model ID
            //'thinkingLevel' => 'high', // 'minimal' or 'high' ('medium' too on Nano Banana 2.1), null uses the provider default
            //'analysisModel' => 'gemini-3.8-flash',
            //'outputFormat' => 'png', // 'png' or 'jpeg'
            //'maxResolution' => '2K', // cap Gemini's tiers (it natively goes to 4K)
        ],
        'openai' => [
            'apiKey' => env('OPENAI_API_KEY'),
            //'defaultModel' => 'gpt-image-2.6-flare', // or 'gpt-image-2.6-sunburst', 'gpt-image-2.5-flare', 'gpt-image-2.5-sunburst', 'gpt-image-2'
            //'quality' => 'auto', // 'auto', 'low', 'medium', 'high', or 'xhigh'/'max' on the 2.5 and 2.6 models
            //'complexity' => null, // 'low', 'medium' or 'high', 2.6 models only; null uses 'low' for precise edits and the API default otherwise
            //'analysisModel' => 'gpt-6.1-terra',
            //'outputFormat' => 'png', // 'png', 'jpeg' or 'webp'
        ],
        // FLUX has no vision model, so pair it with `analysisDriver` (above)
        // to get focal point detection and descriptive filenames.
        'flux' => [
            'apiKey' => env('FLUX_API_KEY'),
            //'defaultModel' => 'flux-3-image', // or 'flux-3-image-klein', 'flux-2-max', 'flux-2-pro', 'flux-2-flex', 'flux-2-klein-9b'
            //'outputFormat' => 'png', // 'png', 'jpeg' or 'webp' (FLUX 3 Image Klein: 'png' or 'jpeg'; FLUX 3 Image picks its own)
            //'safetyTolerance' => 2, // 0 (strict) to 5 (permissive), or up to 7 on the FLUX 3 models if your account is approved for it
            //'grounding' => false, // FLUX 3 Image only: let the model search the web for the prompt
        ],
        // Grok has its own vision model, so it does the analysis tasks
        // in-provider; no `analysisDriver` needed.
        'grok' => [
            'apiKey' => env('XAI_API_KEY'),
            //'defaultModel' => 'grok-imagine-image-2.0', // or 'grok-imagine-image'
            //'quality' => 'auto', // 'auto', 'low' or 'medium'; Grok Imagine 2.0 only
            //'analysisModel' => 'grok-4.7', // vision model for focal point / filenames
        ],
    ],

    // Cross-cutting settings, applied regardless of driver (each is
    // overridable per driver in its `driverConfig` block above).
    //'workingResolution' => '1K',
    //'finalResolution' => '2K',
    //'maxResolution' => '2K', // cap the tiers offered across all drivers
    //'requestTimeout' => 120,
    //'purgeSessionsAfterHours' => 48,
    //'maxRequestsPerMinute' => 20, // per-user cap on edit/generate/finalize requests; 0 disables the throttle
    //'autoFocalPoint' => true,
    //'descriptiveFilenames' => true,
    // One-click quick actions shown as chips in the editor composer. Each
    // needs a `label` and `prompt`; `precise` (optional) overrides the
    // "Precise edits" toggle for that action.
    //'presets' => [
    //    ['label' => 'Remove background', 'prompt' => 'Remove the background, leaving a clean transparent or white backdrop.', 'precise' => true],
    //    ['label' => 'Enhance', 'prompt' => 'Improve the lighting, sharpness and color balance.', 'precise' => true],
    //    ['label' => 'Black & white', 'prompt' => 'Convert to a rich black and white photograph.'],
    //],
    //'aiGeneratedField' => 'isAiGenerated', // handle of a lightswitch field to switch on when an asset is AI-edited/generated
    //'finalizePrompt' => 'Reproduce this exact image faithfully, at maximum resolution and quality. Do not change, add or remove anything.',
    //'preserveInstructions' => 'Keep everything else in the image exactly the same, preserving the original style, lighting, and composition. Do not add, remove, or alter anything that was not explicitly requested.',
];
