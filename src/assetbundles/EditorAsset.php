<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\View;

/**
 * Asset bundle for the AI image editor modal.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class EditorAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'editor.js',
        ];

        $this->css = [
            'editor.css',
        ];

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('ai-image-editor', [
                'AI Image Editor',
                'Accept & Save',
                'and',
                'Apply',
                'Aspect ratio',
                'Auto',
                'Back to editing',
                'Compare the draft and final versions, then choose which one to save.',
                'Describe the change you want to make…',
                'Describe the image you want to create, then apply to generate the first draft.',
                'Describe the image you want to create…',
                'Discard',
                'Discard this session and all edits?',
                'Draft',
                'Edited image saved as {filename}.',
                'Final',
                'Final resolution',
                'Generate',
                'Generating the final version…',
                'Images and prompts are sent to {provider} for processing.',
                'Match original',
                'Model',
                'Original',
                'Output format',
                'Precise edits',
                'Quick actions',
                'Replace the original image? This will permanently overwrite the current file.',
                'Retry',
                'Revert to original',
                'Revert to the original image? All edits will be discarded.',
                'Revert to this version',
                'Revert to this version? Any drafts made after it will be discarded.',
                'Save',
                'Save as a new asset',
                'The edit could not be performed. Please try again.',
                'The image could not be loaded.',
                'The high resolution version could not be generated.',
                'Turn {number}',
                'Working…',
            ]);
        }
    }
}
