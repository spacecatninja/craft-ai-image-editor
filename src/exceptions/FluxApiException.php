<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\exceptions;

/**
 * Exception thrown when a request against the Black Forest Labs (FLUX) API fails.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class FluxApiException extends EditDriverException
{
    // Public Properties
    // =========================================================================

    /**
     * @var bool Whether the failure was a content-policy refusal rather than
     * a technical error.
     */
    public bool $isRefusal = false;

    /**
     * @var int|null The number of seconds to wait before retrying, from the
     * `Retry-After` header on rate-limited responses.
     */
    public ?int $retryAfter = null;

    /**
     * @var int|null The HTTP status code of the failed response, if any.
     */
    public ?int $statusCode = null;
}
