<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Drand\Client\Http\CachingHttpClient;
use Drand\Client\Http\HttpClientInterface;
use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;
use Tests\ValueObject\DummyClient;

/**
 * @covers \Drand\Client\Http\CachingHttpClient
 */
final class CachingHttpClientTest extends TestCase
{
    /**
     * Test that data is cached within the TTL.
     *
     * @return void
     */
    public function testReturnsCachedDataWithinTtl(): void
    {
        $chainInfo = [
            'public_key' => 'pk',
            'period' => 30,
            'genesis_time' => 1000,
            'hash' => 'h',
            'groupHash' => 'g',
            'schemeID' => 'pedersen-bls-chained',
            'metadata' => ['beaconID' => 'mainnet']
        ];
        $beacon = [
            'round' => 1,
            'randomness' => 'r',
            'signature' => 's'
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $cache = new CachingHttpClient($client, 10, 10); // 10s TTL

        self::assertEquals($chainInfo, $cache->getChainInfo()->toArray());
        self::assertEquals($chainInfo, $cache->getChainInfo()->toArray()); // Should be cached
        self::assertEquals($beacon, $cache->getBeacon(1)->toArray());
        self::assertEquals($beacon, $cache->getBeacon(1)->toArray()); // Should be cached
    }

    /**
     * Test that cache expires after TTL.
     *
     * @return void
     */
    public function testCacheExpiresAfterTtl(): void
    {
        $calls = 0;
        $dummy = new class () implements HttpClientInterface {
            public int $calls;
            public function getChainInfo(): Chain
            {
                $this->calls++;
                return Chain::fromArray([
                    'public_key' => 'pk',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'hash' => 'h',
                    'groupHash' => 'g',
                    'schemeID' => 'pedersen-bls-chained',
                    'metadata' => ['beaconID' => 'mainnet']
                ]);
            }
            public function getBeacon(int $round): Beacon
            {
                return Beacon::fromArray([
                    'round' => 1,
                    'randomness' => 'r',
                    'signature' => 's'
                ]);
            }
            public function getLatestBeacon(): Beacon
            {
                return Beacon::fromArray([
                    'round' => 1,
                    'randomness' => 'r',
                    'signature' => 's'
                ]);
            }
        };
        $dummy->calls = &$calls;
        $cache = new CachingHttpClient($dummy, 1, 1); // 1s TTL
        $cache->getChainInfo();
        sleep(2); // Wait for TTL to expire
        $cache->getChainInfo();
        self::assertGreaterThanOrEqual(2, $dummy->calls);
    }

    /**
     * Test that clearCache resets the cache.
     *
     * @return void
     */
    public function testClearCache(): void
    {
        $calls = 0;
        $dummy = new class () implements HttpClientInterface {
            public int $calls;
            public function getChainInfo(): Chain
            {
                $this->calls++;
                return Chain::fromArray([
                    'public_key' => 'pk',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'hash' => 'h',
                    'groupHash' => 'g',
                    'schemeID' => 'pedersen-bls-chained',
                    'metadata' => ['beaconID' => 'mainnet']
                ]);
            }
            public function getBeacon(int $round): Beacon
            {
                return Beacon::fromArray([
                    'round' => 1,
                    'randomness' => 'r',
                    'signature' => 's'
                ]);
            }
            public function getLatestBeacon(): Beacon
            {
                return Beacon::fromArray([
                    'round' => 1,
                    'randomness' => 'r',
                    'signature' => 's'
                ]);
            }
        };
        $dummy->calls = &$calls;
        $cache = new CachingHttpClient($dummy, 100, 100);
        $cache->getChainInfo();
        $cache->clearCache();
        $cache->getChainInfo();
        self::assertGreaterThanOrEqual(2, $dummy->calls);
    }
}
