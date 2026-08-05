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
use spacecatninja\aiimageeditor\drivers\EditDriverInterface;

use Throwable;
use yii\console\ExitCode;

/**
 * Validates the plugin configuration and the connection to the configured provider.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class TestConnectionController extends Controller
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $defaultAction = 'index';

    // Public Methods
    // =========================================================================

    /**
     * Validates the configured credentials against the provider API.
     *
     * @return int
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function actionIndex(): int
    {
        $plugin = AiImageEditor::$plugin;
        $settings = $plugin?->getSettings();

        if ($plugin === null || $settings === null) {
            $this->failure('The AI Image Editor plugin is not fully initialized.');

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($settings->driver === '') {
            $this->failure('No edit driver is configured. Set `driver` in `config/ai-image-editor.php` (`gemini`, `openai`, `flux` or `grok`).');

            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {
            $editDriver = $plugin->getDrivers()->getDriver($settings->driver);
            $analysisDriver = $plugin->getDrivers()->getAnalysisDriver();
        } catch (Throwable $throwable) {
            $this->failure("Could not create the configured driver: {$throwable->getMessage()}");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$this->_testDriver($editDriver, 'edit')) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // The analysis driver only needs a separate check when it's a
        // different provider than the edit driver.
        if ($analysisDriver->getHandle() !== $editDriver->getHandle()) {
            if (!$this->_testDriver($analysisDriver, 'analysis')) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        return ExitCode::OK;
    }

    // Private Methods
    // =========================================================================

    /**
     * Tests the connection for a single driver, printing the outcome.
     *
     * @param EditDriverInterface $driver
     * @param string $role a label for the driver's role, e.g. `edit` or `analysis`
     * @return bool whether the connection test passed
     */
    private function _testDriver(EditDriverInterface $driver, string $role): bool
    {
        $this->stdout("Testing {$role} driver \"{$driver->getHandle()}\" ({$driver->getName()})...\n");

        try {
            $driver->testConnection();
        } catch (Throwable $throwable) {
            $this->failure("Connection test failed: {$throwable->getMessage()}");

            return false;
        }

        $this->success("Connection to {$driver->getName()} verified.");

        return true;
    }
}
