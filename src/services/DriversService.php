<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\services;

use craft\base\Component;

use spacecatninja\aiimageeditor\AiImageEditor;
use spacecatninja\aiimageeditor\drivers\EditDriverInterface;

use yii\base\InvalidConfigException;

/**
 * The Drivers service maintains the registry of available edit drivers and
 * creates driver instances.
 *
 * An instance of the service is available via `AiImageEditor::$plugin->getDrivers()`.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class DriversService extends Component
{
    // Static Properties
    // =========================================================================

    /**
     * @var array<string, class-string> Registered driver classes, indexed by handle.
     */
    private static array $_driverClasses = [];

    // Private Properties
    // =========================================================================

    /**
     * @var array<string, EditDriverInterface> Instantiated drivers, indexed by handle.
     */
    private array $_drivers = [];

    // Public Methods
    // =========================================================================

    /**
     * Registers an edit driver class for a handle.
     *
     * @param string $handle
     * @param string $class
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public static function registerDriver(string $handle, string $class): void
    {
        self::$_driverClasses[$handle] = $class;
    }

    /**
     * Returns all registered driver classes, indexed by handle.
     *
     * @return array<string, class-string>
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public static function getRegisteredDrivers(): array
    {
        return self::$_driverClasses;
    }

    /**
     * Returns the driver configured as the plugin's default.
     *
     * @return EditDriverInterface
     * @throws InvalidConfigException if the configured driver handle is unknown or invalid
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getDefaultDriver(): EditDriverInterface
    {
        $settings = AiImageEditor::$plugin?->getSettings();

        return $this->getDriver($settings?->driver ?? '');
    }

    /**
     * Returns the driver used for image analysis tasks (focal point detection,
     * descriptive filenames). This is the `analysisDriver` setting when set,
     * otherwise the given fallback, otherwise the plugin's default driver. It
     * lets analysis run on a different provider than editing, e.g. a Gemini
     * analysis driver behind a FLUX edit driver.
     *
     * @param string|null $fallbackHandle the edit driver to fall back to when no analysis driver is configured
     * @return EditDriverInterface
     * @throws InvalidConfigException if the resolved handle is unknown or invalid
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getAnalysisDriver(?string $fallbackHandle = null): EditDriverInterface
    {
        $settings = AiImageEditor::$plugin?->getSettings();
        $handle = $settings?->analysisDriver;

        if ($handle === null || $handle === '') {
            $handle = $fallbackHandle ?? $settings?->driver ?? '';
        }

        return $this->getDriver($handle);
    }

    /**
     * Returns the driver instance for the given handle.
     *
     * @param string $handle
     * @return EditDriverInterface
     * @throws InvalidConfigException if the handle is unknown, or the registered class does not implement EditDriverInterface
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getDriver(string $handle): EditDriverInterface
    {
        if (isset($this->_drivers[$handle])) {
            return $this->_drivers[$handle];
        }

        $class = self::$_driverClasses[$handle] ?? null;

        if ($class === null) {
            throw new InvalidConfigException("No edit driver is registered for handle \"{$handle}\".");
        }

        $driver = new $class();

        if (!$driver instanceof EditDriverInterface) {
            throw new InvalidConfigException("Edit driver \"{$class}\" does not implement EditDriverInterface.");
        }

        $this->_drivers[$handle] = $driver;

        return $driver;
    }
}
