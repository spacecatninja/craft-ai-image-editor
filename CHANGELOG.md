# AI Image Editor Changelog

## Unreleased

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
