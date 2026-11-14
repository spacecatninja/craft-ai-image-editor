<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Services;

use Illuminate\Container\Attributes\Singleton;
use SpaceCatNinja\AiImageEditor\Drivers\EditDriverInterface;
use SpaceCatNinja\AiImageEditor\Drivers\EditDrivers;
use SpaceCatNinja\AiImageEditor\Exceptions\EditDriverException;
use SpaceCatNinja\AiImageEditor\Settings;

/**
 * Creates edit driver instances from the drivers registered in {@see EditDrivers}.
 *
 * @author André Elvan
 * @since 1.0.0
 */
#[Singleton]
class DriversService
{
    /**
     * @var array<string, EditDriverInterface> Instantiated drivers, indexed by handle.
     */
    private array $drivers = [];

    public function __construct(
        private readonly EditDrivers $registry,
    ) {
    }

    /**
     * Returns the driver configured as the plugin's default.
     *
     * @throws EditDriverException if the configured driver handle is unknown
     */
    public function getDefaultDriver(): EditDriverInterface
    {
        return $this->getDriver(app(Settings::class)->driver);
    }

    /**
     * Returns the driver used for image analysis tasks (focal point detection,
     * descriptive filenames). This is the `analysisDriver` setting when set,
     * otherwise the given fallback, otherwise the plugin's default driver. It
     * lets analysis run on a different provider than editing, e.g. a Gemini
     * analysis driver behind a FLUX edit driver.
     *
     * @param string|null $fallbackHandle the edit driver to fall back to when no analysis driver is configured
     * @throws EditDriverException if the resolved handle is unknown
     */
    public function getAnalysisDriver(?string $fallbackHandle = null): EditDriverInterface
    {
        $settings = app(Settings::class);
        $handle = $settings->analysisDriver;

        if ($handle === null || $handle === '') {
            $handle = $fallbackHandle ?? $settings->driver;
        }

        return $this->getDriver($handle);
    }

    /**
     * Returns the driver instance for the given handle.
     *
     * @throws EditDriverException if no driver is registered for the handle
     */
    public function getDriver(string $handle): EditDriverInterface
    {
        if (isset($this->drivers[$handle])) {
            return $this->drivers[$handle];
        }

        $class = $this->registry->get($handle);

        if ($class === null) {
            throw new EditDriverException("No edit driver is registered for handle \"{$handle}\".");
        }

        return $this->drivers[$handle] = app($class);
    }
}
