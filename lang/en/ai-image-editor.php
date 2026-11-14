<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

/**
 * The plugin's source (English) strings, covering both the PHP messages and the
 * editor interface (`src/assetbundles/dist/editor.js`), all in the
 * `ai-image-editor` category. Copy this file to another locale directory
 * (e.g. `translations/nb/ai-image-editor.php`) and translate the values.
 *
 * Keep placeholders (`{driver}`, `{provider}`, `{filename}`, `{number}`), code
 * paths in backticks, and the product name "AI Image Editor" intact.
 */

return [
    // Plugin, permissions, entry points
    'AI Image Editor' => 'AI Image Editor',
    'Edit images with AI' => 'Edit images with AI',
    'Generate images with AI' => 'Generate images with AI',
    'Edit with AI' => 'Edit with AI',
    'Purging stale AI image edit sessions' => 'Purging stale AI image edit sessions',

    // Editor: buttons and actions
    'Save' => 'Save',
    'Save as a new asset' => 'Save as a new asset',
    'Accept & Save' => 'Accept & Save',
    'Discard' => 'Discard',
    'Apply' => 'Apply',
    'Generate' => 'Generate',
    'Retry' => 'Retry',
    'Back to editing' => 'Back to editing',
    'Revert to original' => 'Revert to original',
    'Revert to this version' => 'Revert to this version',
    'Quick actions' => 'Quick actions',

    // Editor: composer labels and controls
    'Precise edits' => 'Precise edits',
    'Model' => 'Model',
    'Aspect ratio' => 'Aspect ratio',
    'Output format' => 'Output format',
    'Final resolution' => 'Final resolution',
    'Match original' => 'Match original',
    'Auto' => 'Auto',
    'Draft' => 'Draft',
    'Final' => 'Final',
    'Original' => 'Original',
    'Turn {number}' => 'Turn {number}',
    'and' => 'and',

    // Editor: placeholders, hints and status
    'Describe the change you want to make…' => 'Describe the change you want to make…',
    'Describe the image you want to create…' => 'Describe the image you want to create…',
    'Describe the image you want to create, then apply to generate the first draft.' => 'Describe the image you want to create, then apply to generate the first draft.',
    'Compare the draft and final versions, then choose which one to save.' => 'Compare the draft and final versions, then choose which one to save.',
    'Images and prompts are sent to {provider} for processing.' => 'Images and prompts are sent to {provider} for processing.',
    'Working…' => 'Working…',
    'Generating the final version…' => 'Generating the final version…',
    'Edited image saved as {filename}.' => 'Edited image saved as {filename}.',
    'The image could not be loaded.' => 'The image could not be loaded.',

    // Editor: confirmations
    'Discard this session and all edits?' => 'Discard this session and all edits?',
    'Revert to the original image? All edits will be discarded.' => 'Revert to the original image? All edits will be discarded.',
    'Revert to this version? Any drafts made after it will be discarded.' => 'Revert to this version? Any drafts made after it will be discarded.',

    // Configuration / connection
    'The configured edit driver could not be created. Check `config/ai-image-editor.php`.' => 'The configured edit driver could not be created. Check `config/ai-image-editor.php`.',
    '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.' => '{driver} is not configured. Add your API key in `config/ai-image-editor.php`.',

    // Session / turn flow
    'This session is no longer active.' => 'This session is no longer active.',
    'Please enter an instruction.' => 'Please enter an instruction.',
    'The edit could not be performed. Please try again.' => 'The edit could not be performed. Please try again.',
    'The high resolution version could not be generated.' => 'The high resolution version could not be generated.',
    'You are making requests too quickly. Please wait a moment and try again.' => 'You are making requests too quickly. Please wait a moment and try again.',
    'Only image assets can be edited.' => 'Only image assets can be edited.',
    'Vector images can not be edited.' => 'Vector images can not be edited.',
    'The source image could not be read.' => 'The source image could not be read.',
    'The source image could not be copied into the edit session.' => 'The source image could not be copied into the edit session.',
    'The working image for this session no longer exists. It may have been purged, please start a new session.' => 'The working image for this session no longer exists. It may have been purged, please start a new session.',
    'The result image could not be read back for storage.' => 'The result image could not be read back for storage.',
    'The edit was cancelled.' => 'The edit was cancelled.',

    // Saving / finalizing
    'There are no edits to finalize in this session.' => 'There are no edits to finalize in this session.',
    'There is no result to save in this session.' => 'There is no result to save in this session.',
    'The result image no longer exists.' => 'The result image no longer exists.',
    'The result image could not be prepared for saving.' => 'The result image could not be prepared for saving.',
    'The target folder for this session no longer exists.' => 'The target folder for this session no longer exists.',
    'The source asset for this session no longer exists.' => 'The source asset for this session no longer exists.',
    'The original asset for this session no longer exists.' => 'The original asset for this session no longer exists.',
    'The source asset\'s folder could not be resolved.' => 'The source asset\'s folder could not be resolved.',
    'The original image could not be replaced.' => 'The original image could not be replaced.',
    'The edited image could not be saved as an asset.' => 'The edited image could not be saved as an asset.',
    'A generated image has no original to replace.' => 'A generated image has no original to replace.',
    'The save was cancelled.' => 'The save was cancelled.',
];
