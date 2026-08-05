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

/**
 * Provides the plugin's service components and typed accessors for them.
 *
 * @property DriversService  $drivers
 * @property SessionsService $sessions
 * @property FinalizeService $finalize
 *
 * @author André Elvan
 * @since 1.0.0
 */
trait ServicesTrait
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'drivers' => ['class' => DriversService::class],
                'sessions' => ['class' => SessionsService::class],
                'finalize' => ['class' => FinalizeService::class],
            ],
        ];
    }

    /**
     * Returns the drivers service.
     *
     * @return DriversService
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getDrivers(): DriversService
    {
        $component = $this->get('drivers');
        assert($component instanceof DriversService);

        return $component;
    }

    /**
     * Returns the sessions service.
     *
     * @return SessionsService
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getSessions(): SessionsService
    {
        $component = $this->get('sessions');
        assert($component instanceof SessionsService);

        return $component;
    }

    /**
     * Returns the finalize service.
     *
     * @return FinalizeService
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function getFinalize(): FinalizeService
    {
        $component = $this->get('finalize');
        assert($component instanceof FinalizeService);

        return $component;
    }
}
