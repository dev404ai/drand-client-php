<?php

declare(strict_types=1);

namespace Drand\Client\Http;

use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;

/**
 * HTTP client decorator that adds caching for chain info and beacons.
 *
 * Wraps another HttpClientInterface and caches responses for a configurable TTL.
 *
 * @psalm-suppress UnusedClass
 */
final class CachingHttpClient implements HttpClientInterface
{
    /**
     * @var array<string, array{data: mixed, expires: int}>
     */
    private array $cache = [];

    /**
     * Construct a CachingHttpClient instance.
     *
     * @param HttpClientInterface $client The underlying HTTP client
     * @param int $chainInfoTtl TTL for chain info cache in seconds
     * @param int $beaconTtl TTL for beacon cache in seconds
     */
    public function __construct(
        public readonly HttpClientInterface $client,
        public readonly int $chainInfoTtl = 300, // 5 minutes
        public readonly int $beaconTtl = 30      // 30 seconds
    ) {
    }

    /**
     * Get chain info, using cache if available and valid.
     *
     * @return Chain
     */
    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getChainInfo(): Chain
    {
        $result = $this->getCached('chain_info', function (): Chain {
            return $this->client->getChainInfo();
        }, $this->chainInfoTtl);
        return $result;
    }

    /**
     * Get a beacon for a specific round, using cache if available and valid.
     *
     * @param int $round
     * @return Beacon
     */
    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getBeacon(int $round): Beacon
    {
        $result = $this->getCached("beacon_$round", function () use ($round): Beacon {
            return $this->client->getBeacon($round);
        }, $this->beaconTtl);
        return $result;
    }

    /**
     * Get the latest beacon, bypassing cache.
     *
     * @return Beacon
     */
    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function getLatestBeacon(): Beacon
    {
        $result = $this->client->getLatestBeacon();
        return $result;
    }

    /**
     * Get data from cache or fetch using callback if expired/missing.
     *
     * @template T
     * @param string $key Cache key
     * @param callable():T $callback Function to fetch fresh data
     * @param int $ttl Cache TTL in seconds
     * @return T The cached or fresh data
     */
    private function getCached(string $key, callable $callback, int $ttl): mixed
    {
        $now = time();

        // Return from cache if valid
        if (isset($this->cache[$key]) && $this->cache[$key]['expires'] > $now) {
            return $this->cache[$key]['data'];
        }

        // Fetch fresh data
        $data = $callback();

        // Store in cache
        $this->cache[$key] = [
            'data' => $data,
            'expires' => $now + $ttl
        ];

        return $data;
    }

    /**
     * Clear the internal cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
