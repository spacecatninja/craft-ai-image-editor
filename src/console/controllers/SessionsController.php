<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\console\controllers;

use craft\console\Controller;

use spacecatninja\aiimageeditor\AiImageEditor;

use yii\console\ExitCode;

/**
 * Manages edit sessions from the command line.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class SessionsController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null Age threshold in hours. Defaults to the `purgeSessionsAfterHours` setting.
     */
    public ?int $hours = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'hours';

        return $options;
    }

    /**
     * Purges stale active edit sessions and their temporary files.
     *
     * @return int
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionPurge(): int
    {
        $sessions = AiImageEditor::$plugin?->getSessions();

        if ($sessions === null) {
            $this->failure('The AI Image Editor plugin is not fully initialized.');

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $count = $sessions->purgeStaleSessions($this->hours);
        $this->success("Purged {$count} stale edit session(s).");

        return ExitCode::OK;
    }
}
