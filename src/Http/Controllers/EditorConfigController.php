<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use SpaceCatNinja\AiImageEditor\AiImageEditor;

use function CraftCms\Cms\t;

/**
 * Returns what the editor script needs before it opens: the current user's
 * permissions and the translated interface strings.
 *
 * @since 2.0.0
 */
class EditorConfigController
{
    public function __invoke(): JsonResponse
    {
        // The English file lists every string the plugin uses, the editor's included.
        $messages = require \dirname(__DIR__, 3) . '/lang/en/ai-image-editor.php';

        return response()->json([
            'canEdit' => Gate::check(AiImageEditor::PERMISSION_EDIT),
            'canGenerate' => Gate::check(AiImageEditor::PERMISSION_GENERATE),
            'messages' => collect(array_keys($messages))
                ->mapWithKeys(static fn(string $message) => [$message => t($message, category: 'ai-image-editor')])
                ->all(),
        ]);
    }
}
