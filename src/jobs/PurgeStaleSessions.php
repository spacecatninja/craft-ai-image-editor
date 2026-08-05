<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\jobs;

use Craft;
use craft\queue\BaseJob;

use spacecatninja\aiimageeditor\AiImageEditor;

/**
 * Queue job that purges abandoned active edit sessions and their temporary
 * files, so experiments that were never finalized or discarded don't
 * accumulate disk usage indefinitely.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class PurgeStaleSessions extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null Age threshold in hours. If null, the `purgeSessionsAfterHours`
     * setting is used.
     */
    public ?int $hours = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        AiImageEditor::$plugin?->getSessions()->purgeStaleSessions($this->hours);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return Craft::t('ai-image-editor', 'Purging stale AI image edit sessions');
    }
}
