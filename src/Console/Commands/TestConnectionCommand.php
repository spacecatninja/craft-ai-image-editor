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
use SpaceCatNinja\AiImageEditor\Drivers\EditDriverInterface;
use SpaceCatNinja\AiImageEditor\Services\DriversService;
use SpaceCatNinja\AiImageEditor\Settings;
use Throwable;

/**
 * Verifies that the configured edit driver, and the analysis driver when it's
 * a different provider, can reach their APIs with the configured credentials.
 *
 * @since 2.0.0
 */
class TestConnectionCommand extends Command
{
    use CraftCommand;

    #[\Override]
    protected $signature = 'ai-image-editor:test-connection';

    #[\Override]
    protected $description = 'Tests the connection to the configured AI Image Editor provider(s)';

    #[\Override]
    protected $aliases = ['ai-image-editor/test-connection', 'craft:ai-image-editor:test-connection'];

    public function handle(DriversService $drivers, Settings $settings): int
    {
        if ($settings->driver === '') {
            $this->error('No edit driver is configured. Set `driver` in `config/ai-image-editor.php` (`gemini`, `openai`, `flux` or `grok`).');

            return self::FAILURE;
        }

        try {
            $editDriver = $drivers->getDriver($settings->driver);
            $analysisDriver = $drivers->getAnalysisDriver();
        } catch (Throwable $throwable) {
            $this->error("Could not create the configured driver: {$throwable->getMessage()}");

            return self::FAILURE;
        }

        if (!$this->testDriver($editDriver, 'edit')) {
            return self::FAILURE;
        }

        // The analysis driver only needs a separate check when it's a different provider than the edit driver.
        if ($analysisDriver->getHandle() !== $editDriver->getHandle() && !$this->testDriver($analysisDriver, 'analysis')) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function testDriver(EditDriverInterface $driver, string $role): bool
    {
        $this->line("Testing {$role} driver \"{$driver->getHandle()}\" ({$driver->getName()})...");

        try {
            $driver->testConnection();
        } catch (Throwable $throwable) {
            $this->error("Connection test failed: {$throwable->getMessage()}");

            return false;
        }

        $this->info("Connection to {$driver->getName()} verified.");

        return true;
    }
}
