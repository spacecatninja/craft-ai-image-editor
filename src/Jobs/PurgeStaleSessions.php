<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Jobs;

use CraftCms\Cms\Queue\Job;
use SpaceCatNinja\AiImageEditor\Services\SessionsService;

use function CraftCms\Cms\t;

/**
 * Queue job that purges abandoned active edit sessions and their temporary
 * files, so experiments that were never finalized or discarded don't
 * accumulate disk usage indefinitely.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class PurgeStaleSessions extends Job
{
    /**
     * @param int|null $hours Age threshold in hours. If null, the `purgeSessionsAfterHours` setting is used.
     */
    public function __construct(
        public ?int $hours = null,
    ) {
        parent::__construct();
    }

    public function handle(SessionsService $sessions): void
    {
        $sessions->purgeStaleSessions($this->hours);
    }

    protected function defaultDescription(): string
    {
        return t('Purging stale AI image edit sessions', category: 'ai-image-editor');
    }
}
