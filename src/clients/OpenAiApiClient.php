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

use spacecatninja\aiimageeditor\exceptions\OpenAiApiException;

/**
 * Thin HTTP client for the OpenAI API. All knowledge about endpoint URLs,
 * headers and error payloads lives here, the driver deals in plain arrays.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class OpenAiApiClient
{
    // Const Properties
    // =========================================================================

    public const API_BASE_URL = 'https://api.openai.com/v1/';

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
     * OpenAiApiClient constructor.
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
     * Runs a chat completion, used for image analysis tasks.
     *
     * @param array $params the JSON request body
     * @return array the decoded response
     * @throws OpenAiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function chatCompletion(array $params): array
    {
        return $this->_request('POST', 'chat/completions', ['json' => $params]);
    }

    /**
     * Generates an image from a prompt.
     *
     * @param array $params the JSON request body, see the images/generations endpoint
     * @return array the decoded response
     * @throws OpenAiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function createImage(array $params): array
    {
        return $this->_request('POST', 'images/generations', ['json' => $params]);
    }

    /**
     * Edits an image based on a prompt.
     *
     * @param array $multipart the multipart form data, see the images/edits endpoint
     * @return array the decoded response
     * @throws OpenAiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function editImage(array $multipart): array
    {
        return $this->_request('POST', 'images/edits', ['multipart' => $multipart]);
    }

    /**
     * Lists available models. Used as a cheap, non-billable way to validate credentials.
     *
     * @return array the decoded response
     * @throws OpenAiApiException if the request fails or the response can't be decoded
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function listModels(): array
    {
        return $this->_request('GET', 'models');
    }

    // Private Methods
    // =========================================================================

    /**
     * Performs a request against the API and returns the decoded JSON response.
     *
     * @throws OpenAiApiException if the request fails or the response can't be decoded
     */
    private function _request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->_client()->request($method, $uri, $options);
        } catch (BadResponseException $badResponseException) {
            throw $this->_createApiException($badResponseException);
        } catch (GuzzleException $guzzleException) {
            throw new OpenAiApiException('Could not reach the OpenAI API: ' . $guzzleException->getMessage(), 0, $guzzleException);
        }

        $decoded = Json::decodeIfJson((string)$response->getBody());

        if (!\is_array($decoded)) {
            throw new OpenAiApiException('The OpenAI API returned an unexpected, non-JSON response.');
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
                    'Authorization' => "Bearer {$this->apiKey}",
                ],
            ]);
        }

        return $this->_client;
    }

    /**
     * Converts a Guzzle error response into an OpenAiApiException with a
     * message that is safe to surface, plus status code, refusal and retry
     * metadata.
     */
    private function _createApiException(BadResponseException $badResponseException): OpenAiApiException
    {
        $response = $badResponseException->getResponse();
        $statusCode = $response->getStatusCode();
        $body = Json::decodeIfJson((string)$response->getBody());
        $apiMessage = \is_array($body) ? ($body['error']['message'] ?? null) : null;
        $errorCode = \is_array($body) ? ($body['error']['code'] ?? null) : null;
        $isRefusal = $errorCode === 'moderation_blocked' || $errorCode === 'content_policy_violation';

        if ($isRefusal) {
            $message = $apiMessage ?? 'The request was declined by the provider\'s content policy.';
        } else {
            $message = match (true) {
                $statusCode === 401, $statusCode === 403 => 'The OpenAI API rejected the configured API key.',
                $statusCode === 429 => 'The OpenAI API rate limit was hit. Please wait a moment and try again.',
                default => 'The OpenAI API request failed.',
            };

            if ($apiMessage !== null) {
                $message .= " ({$apiMessage})";
            }
        }

        $exception = new OpenAiApiException($message, $statusCode, $badResponseException);
        $exception->statusCode = $statusCode;
        $exception->isRefusal = $isRefusal;

        $retryAfter = $response->getHeaderLine('Retry-After');

        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            $exception->retryAfter = (int)$retryAfter;
        }

        return $exception;
    }
}
