<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Console\Commands;

use CraftCms\Cms\Console\CraftCommand;
use Illuminate\Console\Command;
use SpaceCatNinja\AiImageEditor\Services\SessionsService;

/**
 * Purges abandoned edit sessions and their temporary files.
 *
 * @since 2.0.0
 */
class PurgeSessionsCommand extends Command
{
    use CraftCommand;

    #[\Override]
    protected $signature = 'ai-image-editor:sessions:purge
        {--hours= : Purge active sessions untouched for this many hours. Defaults to the `purgeSessionsAfterHours` setting.}';

    #[\Override]
    protected $description = 'Purges abandoned AI Image Editor sessions and their temporary files';

    #[\Override]
    protected $aliases = ['ai-image-editor/sessions/purge', 'craft:ai-image-editor:sessions:purge'];

    public function handle(SessionsService $sessions): int
    {
        $hours = $this->option('hours');

        if ($hours !== null && (!ctype_digit((string)$hours) || (int)$hours < 1)) {
            $this->error('--hours must be a positive whole number.');

            return self::INVALID;
        }

        $count = $sessions->purgeStaleSessions($hours !== null ? (int)$hours : null);

        $this->info("Purged {$count} stale edit session(s).");

        return self::SUCCESS;
    }
}
