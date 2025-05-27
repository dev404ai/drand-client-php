<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Drand\Client\Http\HttpClient;
use Drand\Client\DrandClient;
use Drand\Client\Backend\PhpGmpBackend;
use Drand\Client\ValueObject\ChainInfo;
use Drand\Client\ValueObject\BeaconInfo;
use Drand\Client\Enum\SignatureScheme;

/**
 * @covers \Drand\Client\Http\HttpClient
 * @covers \Drand\Client\DrandClient
 * @group integration
 */
final class HttpIntegrationTest extends TestCase
{
    private const ENDPOINT = 'https://pl-us.testnet.drand.sh';

    /**
     * Skip test if DRAND_INTEGRATION is not set.
     */
    protected function setUp(): void
    {
        if (getenv('DRAND_INTEGRATION') !== '1') {
            self::markTestSkipped('Set DRAND_INTEGRATION=1 to enable real HTTP integration tests.');
        }
    }

    /**
     * Test fetching chain info and latest beacon from a real drand node.
     *
     * @return void
     */
    public function testFetchChainInfoAndLatestBeacon(): void
    {
        $http = new HttpClient(self::ENDPOINT);
        $client = new DrandClient($http, new \Drand\Client\Verifier(['gmp' => new PhpGmpBackend()]));
        $chain = $client->getChain();
        $pubKey = $chain->getPublicKey();
        $scheme = $chain->getSchemeID();
        self::assertNotEmpty($chain->getHash());
        self::assertNotEmpty($pubKey);
        self::assertGreaterThan(0, $chain->getPeriod());
        self::assertGreaterThan(0, $chain->getGenesisTime());
        $latest = $http->getLatestBeacon();
        $signature = $latest->getSignature();
        fwrite(STDERR, "raw public_key: $pubKey\n");
        fwrite(STDERR, "raw signature: " . $signature . "\n");
        $pubKeyHex = hex2bin($pubKey);
        fwrite(STDERR, "hex2bin public_key length: " . (is_string($pubKeyHex) ? strlen($pubKeyHex) : 'N/A') . "\n");
        $signatureHex = hex2bin($signature);
        fwrite(STDERR, "hex2bin signature length: " . (is_string($signatureHex) ? strlen($signatureHex) : 'N/A') . "\n");
        $pubKeyBase64 = base64_decode($pubKey, true);
        fwrite(STDERR, "base64_decode public_key length: " . (is_string($pubKeyBase64) ? strlen($pubKeyBase64) : 'N/A') . "\n");
        $signatureBase64 = base64_decode($signature, true);
        fwrite(STDERR, "base64_decode signature length: " . (is_string($signatureBase64) ? strlen($signatureBase64) : 'N/A') . "\n");
        return;
    }
}
