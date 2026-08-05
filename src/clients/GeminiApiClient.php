<?php
/**
 * AI Image Editor plugin for Craft CMS
 *
 * Edit images in the Craft CMS control panel using natural language, powered by AI.
 *
 * @link      https://www.spacecat.ninja
 * @copyright Copyright (c) 2026 André Elvan
 */

namespace spacecatninja\aiimageeditor\clients;

use Craft;
use craft\helpers\Json;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;

use spacecatninja\aiimageeditor\exceptions\GeminiApiException;

/**
 * Thin HTTP client for the Gemini API. All knowledge about endpoint URLs,
 * headers and error payloads lives here, the driver deals in plain arrays.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class GeminiApiClient
{
    // Const Properties
    // =========================================================================

    public const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/';

    // Private Properties
    // =========================================================================

    /**
     * @var Client|null
     * @see _client()
     */
    private ?Client $_client = null;

    // Public Methods
    // =========================================================================

    /**
     * GeminiApiClient constructor.
     *
     * @param string $apiKey
     * @param int    $timeout request timeout in seconds
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 120,
    ) {
    }

    /**
     * Creates an image interaction, i.e. one generate/edit request.
     *
     * @param array $payload the full request payload, see the Gemini interactions API
     * @return array the decoded response
     * @throws GeminiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function createImageInteraction(array $payload): array
    {
        return $this->_request('POST', 'interactions', ['json' => $payload]);
    }

    /**
     * Creates a text interaction, e.g. an image analysis request whose result
     * is text rather than an image.
     *
     * @param array $payload the full request payload, see the Gemini interactions API
     * @return array the decoded response
     * @throws GeminiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function createTextInteraction(array $payload): array
    {
        return $this->_request('POST', 'interactions', ['json' => $payload]);
    }

    /**
     * Lists available models. Used as a cheap, non-billable way to validate credentials.
     *
     * @param int $pageSize
     * @return array the decoded response
     * @throws GeminiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function listModels(int $pageSize = 1): array
    {
        return $this->_request('GET', 'models', ['query' => ['pageSize' => $pageSize]]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Performs a request against the API and returns the decoded JSON response.
     *
     * @param string $method
     * @param string $uri
     * @param array  $options
     * @return array
     * @throws GeminiApiException if the request fails or the response can't be decoded
     */
    private function _request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->_client()->request($method, $uri, $options);
        } catch (BadResponseException $badResponseException) {
            throw $this->_createApiException($badResponseException);
        } catch (GuzzleException $guzzleException) {
            $exception = new GeminiApiException('Could not reach the Gemini API: ' . $guzzleException->getMessage(), 0, $guzzleException);

            throw $exception;
        }

        $decoded = Json::decodeIfJson((string)$response->getBody());

        if (!\is_array($decoded)) {
            throw new GeminiApiException('The Gemini API returned an unexpected, non-JSON response.');
        }

        return $decoded;
    }

    /**
     * Returns the Guzzle client, creating it on first use.
     */
    private function _client(): Client
    {
        if ($this->_client === null) {
            $this->_client = Craft::createGuzzleClient([
                'base_uri' => self::API_BASE_URL,
                'timeout' => $this->timeout,
                'headers' => [
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);
        }

        return $this->_client;
    }

    /**
     * Converts a Guzzle error response into a GeminiApiException with a
     * message that is safe to surface, plus status code and retry metadata.
     */
    private function _createApiException(BadResponseException $badResponseException): GeminiApiException
    {
        $response = $badResponseException->getResponse();
        $statusCode = $response->getStatusCode();
        $body = Json::decodeIfJson((string)$response->getBody());
        $apiMessage = \is_array($body) ? ($body['error']['message'] ?? null) : null;

        $message = match (true) {
            $statusCode === 401, $statusCode === 403 => 'The Gemini API rejected the configured API key.',
            $statusCode === 429 => 'The Gemini API rate limit was hit. Please wait a moment and try again.',
            default => 'The Gemini API request failed.',
        };

        if ($apiMessage !== null) {
            $message .= " ({$apiMessage})";
        }

        $exception = new GeminiApiException($message, $statusCode, $badResponseException);
        $exception->statusCode = $statusCode;

        $retryAfter = $response->getHeaderLine('Retry-After');

        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            $exception->retryAfter = (int)$retryAfter;
        }

        return $exception;
    }
}
