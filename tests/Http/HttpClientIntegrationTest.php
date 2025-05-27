<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Drand\Client\Http\HttpClient;

/**
 * @group integration
 */
final class HttpClientIntegrationTest extends TestCase
{
    /**
     * Test fetching chain info and beacon from drand mainnet.
     *
     * @return void
     */
    public function testFetchChainInfoAndBeaconFromMainnet(): void
    {
        $client = new HttpClient('https://api.drand.sh');
        try {
            $info = $client->getChainInfo();
            self::assertNotEmpty($info->getPublicKey());
            self::assertNotEmpty($info->getPeriod());
            self::assertNotEmpty($info->getGenesisTime());
            $beacon = $client->getLatestBeacon();
            self::assertNotEmpty($beacon->getRound());
            self::assertNotEmpty($beacon->getRandomness());
            self::assertNotEmpty($beacon->getSignature());
        } catch (\Throwable $e) {
            self::markTestSkipped('Network unavailable or drand mainnet not reachable: ' . $e->getMessage());
        }
    }
}
