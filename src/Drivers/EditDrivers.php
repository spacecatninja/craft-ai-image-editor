<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace SpaceCatNinja\AiImageEditor\Drivers;

use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * The edit drivers, keyed by handle.
 *
 * Plugins register their own drivers in their `boot()`:
 *
 * ```php
 * public function boot(EditDrivers $drivers): void
 * {
 *     $drivers->register('my-provider', MyEditDriver::class);
 * }
 * ```
 */
#[Singleton]
class EditDrivers
{
    /**
     * @var array<string, class-string<EditDriverInterface>>
     */
    private array $drivers = [
        GeminiEditDriver::HANDLE => GeminiEditDriver::class,
        OpenAiEditDriver::HANDLE => OpenAiEditDriver::class,
        FluxEditDriver::HANDLE => FluxEditDriver::class,
        GrokEditDriver::HANDLE => GrokEditDriver::class,
    ];

    /**
     * Registers a driver class under a handle. Registering an existing handle replaces it.
     *
     * @param class-string<EditDriverInterface> $class
     */
    public function register(string $handle, string $class): void
    {
        if (!is_a($class, EditDriverInterface::class, true)) {
            throw new InvalidArgumentException(sprintf('[%s] must implement [%s].', $class, EditDriverInterface::class));
        }

        $this->drivers[$handle] = $class;
    }

    public function remove(string $handle): void
    {
        unset($this->drivers[$handle]);
    }

    public function has(string $handle): bool
    {
        return isset($this->drivers[$handle]);
    }

    /**
     * @return class-string<EditDriverInterface>|null
     */
    public function get(string $handle): ?string
    {
        return $this->drivers[$handle] ?? null;
    }

    /**
     * @return array<string, class-string<EditDriverInterface>>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
