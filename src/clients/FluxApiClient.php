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

use spacecatninja\aiimageeditor\exceptions\FluxApiException;

/**
 * Thin HTTP client for the Black Forest Labs (FLUX) API.
 *
 * Unlike the Gemini and OpenAI clients this one is asynchronous: a request is
 * submitted, then a polling URL is polled until the result is ready, then the
 * signed result URL is downloaded. The whole dance is encapsulated in
 * {@see submitAndAwait()}, so callers still deal in one blocking method.
 *
 * @author André Elvan
 * @since 1.0.0
 */
class FluxApiClient
{
    // Const Properties
    // =========================================================================

    public const API_BASE_URL = 'https://api.bfl.ai/v1/';

    /**
     * @var int Milliseconds between result polls.
     */
    private const POLL_INTERVAL_MS = 700;

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
     * FluxApiClient constructor.
     *
     * @param string $apiKey
     * @param int    $timeout total time budget in seconds, spanning submission,
     *                        polling and download
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 120,
    ) {
    }

    /**
     * Submits a request to a model, polls until it is ready, and downloads the
     * result image.
     *
     * @param string $model the model endpoint, e.g. `flux-2-pro`
     * @param array  $payload the request body
     * @return array{data: string, mimeType: string} the image bytes and mime type
     * @throws FluxApiException if the request fails, is moderated, or times out
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function submitAndAwait(string $model, array $payload): array
    {
        $submission = $this->_request('POST', $model, ['json' => $payload]);
        $pollingUrl = $submission['polling_url'] ?? null;

        if (!\is_string($pollingUrl) || $pollingUrl === '') {
            throw new FluxApiException('The FLUX API did not return a polling URL.');
        }

        $sampleUrl = $this->_poll($pollingUrl);

        return $this->_download($sampleUrl);
    }

    /**
     * Performs a lightweight authenticated request to validate credentials.
     *
     * @return bool
     * @throws FluxApiException if the credentials are rejected or the API can't be reached
     *
     * @author André Elvan
     * @since 1.0.0
     */
    public function ping(): bool
    {
        try {
            $this->_client()->request('GET', 'get_result', ['query' => ['id' => 'ping']]);
        } catch (BadResponseException $badResponseException) {
            $statusCode = $badResponseException->getResponse()->getStatusCode();

            // A rejected key is 401/403. Anything else (e.g. 404 for the bogus
            // id) means we reached the API and authenticated fine.
            if ($statusCode === 401 || $statusCode === 403) {
                throw $this->_createApiException($badResponseException);
            }
        } catch (GuzzleException $guzzleException) {
            throw new FluxApiException('Could not reach the FLUX API: ' . $guzzleException->getMessage(), 0, $guzzleException);
        }

        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Polls the given URL until the result is ready, and returns the signed
     * result image URL.
     *
     * @throws FluxApiException if the request is moderated, errors, or times out
     */
    private function _poll(string $pollingUrl): string
    {
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $result = $this->_request('GET', $pollingUrl);
            $status = $result['status'] ?? '';

            if ($status === 'Ready') {
                $sampleUrl = $result['result']['sample'] ?? null;

                if (!\is_string($sampleUrl) || $sampleUrl === '') {
                    throw new FluxApiException('The FLUX API reported a ready result without an image.');
                }

                return $sampleUrl;
            }

            if (stripos($status, 'moderated') !== false) {
                $exception = new FluxApiException('The request was declined by the provider\'s content policy.');
                $exception->isRefusal = true;

                throw $exception;
            }

            if ($status === 'Error' || $status === 'Failed') {
                $detail = \is_string($result['details'] ?? null) ? " ({$result['details']})" : '';

                throw new FluxApiException("The FLUX API request failed.{$detail}");
            }

            usleep(self::POLL_INTERVAL_MS * 1000);
        }

        throw new FluxApiException('The FLUX API request timed out.');
    }

    /**
     * Downloads the signed result image. The URL is pre-signed, so it's
     * fetched without the API credentials.
     *
     * @return array{data: string, mimeType: string}
     * @throws FluxApiException if the download fails
     */
    private function _download(string $sampleUrl): array
    {
        try {
            $response = Craft::createGuzzleClient(['timeout' => $this->timeout])->request('GET', $sampleUrl);
        } catch (GuzzleException $guzzleException) {
            throw new FluxApiException('Could not download the FLUX result image: ' . $guzzleException->getMessage(), 0, $guzzleException);
        }

        return [
            'data' => (string)$response->getBody(),
            'mimeType' => $response->getHeaderLine('Content-Type') ?: 'image/png',
        ];
    }

    /**
     * Performs a request against the API and returns the decoded JSON response.
     *
     * @throws FluxApiException if the request fails or the response can't be decoded
     */
    private function _request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->_client()->request($method, $uri, $options);
        } catch (BadResponseException $badResponseException) {
            throw $this->_createApiException($badResponseException);
        } catch (GuzzleException $guzzleException) {
            throw new FluxApiException('Could not reach the FLUX API: ' . $guzzleException->getMessage(), 0, $guzzleException);
        }

        $decoded = Json::decodeIfJson((string)$response->getBody());

        if (!\is_array($decoded)) {
            throw new FluxApiException('The FLUX API returned an unexpected, non-JSON response.');
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
                    'x-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ]);
        }

        return $this->_client;
    }

    /**
     * Converts a Guzzle error response into a FluxApiException with a message
     * that is safe to surface, plus status code and retry metadata.
     */
    private function _createApiException(BadResponseException $badResponseException): FluxApiException
    {
        $response = $badResponseException->getResponse();
        $statusCode = $response->getStatusCode();
        $body = Json::decodeIfJson((string)$response->getBody());
        $apiMessage = \is_array($body) ? ($body['detail'] ?? $body['message'] ?? null) : null;

        // FastAPI validation errors return `detail` as a list of {msg, loc, ...};
        // flatten the messages instead of discarding them.
        if (\is_array($apiMessage)) {
            $messages = array_filter(array_map(
                static fn($item) => \is_array($item) ? ($item['msg'] ?? null) : (\is_string($item) ? $item : null),
                $apiMessage
            ));

            $apiMessage = $messages !== [] ? implode('; ', $messages) : null;
        }

        $message = match (true) {
            $statusCode === 401, $statusCode === 403 => 'The FLUX API rejected the configured API key.',
            $statusCode === 402 => 'The FLUX account is out of credits.',
            $statusCode === 429 => 'The FLUX API rate limit was hit. Please wait a moment and try again.',
            default => 'The FLUX API request failed.',
        };

        if (\is_string($apiMessage) && $apiMessage !== '') {
            $message .= " ({$apiMessage})";
        }

        $exception = new FluxApiException($message, $statusCode, $badResponseException);
        $exception->statusCode = $statusCode;

        $retryAfter = $response->getHeaderLine('Retry-After');

        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            $exception->retryAfter = (int)$retryAfter;
        }

        return $exception;
    }
}
