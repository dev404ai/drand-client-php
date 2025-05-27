<?php

declare(strict_types=1);

namespace Drand\Client\Http;

use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;

/**
 * HTTP client for interacting with a drand node.
 *
 * Provides methods to fetch chain info, beacons, and the latest beacon from a drand HTTP API endpoint.
 */
/** @psalm-suppress UnusedClass */
final class HttpClient implements HttpClientInterface
{
    private const USER_AGENT = 'drand-client-php/1.0';

    private readonly string $baseUrl;
    /** @var array<string, mixed> */
    public readonly array $options;

    /**
     * Construct an HttpClient instance.
     *
     * @param string $baseUrl The base URL of the drand node (e.g., 'https://api.drand.sh')
     * @param array<string, mixed> $options Client options
     */
    public function __construct(
        string $baseUrl,
        array $options = []
    ) {
        // Remove trailing slash from base URL if present
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->options = [
            'disableBeaconVerification' => false,
            'noCache' => false,
            'timeout' => 10,
            ...$options,
        ];
    }

    /**
     * Get chain information from the drand node.
     *
     * @return Chain
     * @throws \RuntimeException If the request fails or verification fails
     */
    #[\Override]
    public function getChainInfo(): Chain
    {
        /** @var array{public_key: string, period: int, genesis_time: int, hash: string, groupHash: string, schemeID: string, metadata: array{beaconID: string}} $response */
        $response = $this->request('/info');
        // Verify chain info if verification params are provided
        if (isset($this->options['chainVerificationParams'])) {
            $params = $this->options['chainVerificationParams'];
            if (is_array($params)) {
                if (isset($params['chainHash']) && $response['hash'] !== $params['chainHash']) {
                    throw new \RuntimeException('Chain hash mismatch');
                }
                if (isset($params['publicKey']) && $response['public_key'] !== $params['publicKey']) {
                    throw new \RuntimeException('Public key mismatch');
                }
            }
        }
        return Chain::fromArray($response);
    }

    /**
     * Get a beacon for a specific round.
     *
     * @param int $round
     * @return Beacon
     * @throws \RuntimeException If the request fails
     */
    #[\Override]
    public function getBeacon(int $round): Beacon
    {
        if ($round < 1) {
            throw new \InvalidArgumentException('Round number must be greater than 0');
        }
        /** @var array{round: int, randomness: string, signature: string, previous_signature?: string|null} $result */
        $result = $this->request("/public/$round");
        return Beacon::fromArray($result);
    }

    /**
     * Get the latest beacon.
     *
     * @return Beacon
     * @throws \RuntimeException If the request fails
     */
    #[\Override]
    public function getLatestBeacon(): Beacon
    {
        /** @var array{round: int, randomness: string, signature: string, previous_signature?: string|null} $result */
        $result = $this->request('/public/latest');
        return Beacon::fromArray($result);
    }

    /**
     * Make an HTTP request to the drand API and decode the JSON response.
     *
     * @param string $path The API endpoint path
     * @return array<string, mixed> The decoded JSON response
     * @throws \RuntimeException If the request fails or response is invalid
     */
    private function request(string $path): array
    {
        $ch = curl_init();
        $url = $this->baseUrl . $path;
        if ((isset($this->options['noCache']) && $this->options['noCache'] === true)) {
            $url .= '?' . time(); // Add timestamp to prevent caching
        }
        $timeout = isset($this->options['timeout']) && is_int($this->options['timeout']) ? $this->options['timeout'] : 10;
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false || !is_string($response)) {
            throw new \RuntimeException(
                "HTTP request failed: $error"
            );
        }
        if ($statusCode !== 200) {
            throw new \RuntimeException(
                "HTTP request failed with status code: $statusCode"
            );
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException(
                'Failed to decode JSON response: ' . json_last_error_msg()
            );
        }
        /** @var array<string, mixed> $data */
        return $data;
    }
}
