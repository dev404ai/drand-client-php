<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Drand\Client\Http\MultiChainClient;
use Drand\Client\Http\FastestNodeClient;
use Drand\Client\DrandClient;
use Drand\Client\ValueObject\Chain;
use Drand\Client\Enum\SignatureScheme;
use Tests\ValueObject\Network;
use Drand\Client\Enum\VerificationMode;
use Drand\Client\Enum\CachePolicy;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\Backend\PhpGmpBackend;
use Tests\ValueObject\DummyClient;

/**
 * @covers \Drand\Client\Http\MultiChainClient
 * @covers \Drand\Client\Http\FastestNodeClient
 * @covers \Drand\Client\DrandClient
 * @group unit
 */
final class MultiAndFastestClientTest extends TestCase
{
    /**
     * Helper to create chain info arrays for tests.
     * @return array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}}
     */
    private function makeChainInfo(string $hash, string $beaconID, string $publicKeyHex): array
    {
        return [
            'hash' => $hash,
            'public_key' => $publicKeyHex,
            'period' => 30,
            'genesis_time' => 1000,
            'groupHash' => 'g',
            'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
            'metadata' => ['beaconID' => $beaconID],
        ];
    }

    /**
     * Test MultiChainClient selection by beaconID and chain hash.
     *
     * @return void
     */
    public function testMultiChainClientSelection(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $sig1 = random_bytes(48);
        $sig2 = random_bytes(48);
        $mainnet = new DummyClient(
            $this->makeChainInfo('main', Network::MAINNET->value, $pubHex),
            [
                'round' => 1,
                'randomness' => bin2hex(hash('sha256', $sig1, true)),
                'signature' => bin2hex($sig1)
            ]
        );
        $testnet = new DummyClient(
            $this->makeChainInfo('test', Network::TESTNET->value, $pubHex),
            [
                'round' => 2,
                'randomness' => bin2hex(hash('sha256', $sig2, true)),
                'signature' => bin2hex($sig2)
            ]
        );
        $multi = new MultiChainClient([$mainnet, $testnet]);
        self::assertSame('main', $multi->forBeaconID(Network::MAINNET->value)->getChainInfo()->getHash());
        self::assertSame('test', $multi->forChainHash('test')->getChainInfo()->getHash());
        self::assertSame(1, $multi->getBeacon(1)->getRound()); // default is first
    }

    /**
     * Test DrandClient with MultiChainClient.
     *
     * @return void
     */
    public function testDrandClientWithMultiChain(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $sig1 = random_bytes(48);
        $sig2 = random_bytes(48);
        $mainnet = new DummyClient(
            $this->makeChainInfo('main', Network::MAINNET->value, $pubHex),
            [
                'round' => 1,
                'randomness' => bin2hex(hash('sha256', $sig1, true)),
                'signature' => bin2hex($sig1)
            ]
        );
        $testnet = new DummyClient(
            $this->makeChainInfo('test', Network::TESTNET->value, $pubHex),
            [
                'round' => 2,
                'randomness' => bin2hex(hash('sha256', $sig2, true)),
                'signature' => bin2hex($sig2)
            ]
        );
        $client = DrandClient::createMultiChain([$mainnet, $testnet], new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]), ['verificationMode' => VerificationMode::DISABLED]);
        self::assertEquals(1, $client->getBeacon(1)->getRound());
    }

    /**
     * Test FastestNodeClient selects the fastest node.
     *
     * @return void
     */
    public function testFastestNodeClient(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $sig1 = random_bytes(48);
        $sig2 = random_bytes(48);
        $fast = new DummyClient(
            $this->makeChainInfo('f', 'f', $pubHex),
            [
                'round' => 1,
                'randomness' => bin2hex(hash('sha256', $sig1, true)),
                'signature' => bin2hex($sig1)
            ],
            1
        );
        $slow = new DummyClient(
            $this->makeChainInfo('s', 's', $pubHex),
            [
                'round' => 2,
                'randomness' => bin2hex(hash('sha256', $sig2, true)),
                'signature' => bin2hex($sig2)
            ],
            100
        );
        $fastest = new FastestNodeClient([$slow, $fast], 1);
        // Should pick the fast client
        self::assertEquals(1, $fastest->getBeacon(1)->getRound());
    }

    /**
     * Test DrandClient with FastestNodeClient.
     *
     * @return void
     */
    public function testDrandClientWithFastestNode(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $sig1 = random_bytes(48);
        $sig2 = random_bytes(48);
        $fast = new DummyClient(
            $this->makeChainInfo('f', 'f', $pubHex),
            [
                'round' => 1,
                'randomness' => bin2hex(hash('sha256', $sig1, true)),
                'signature' => bin2hex($sig1)
            ],
            1
        );
        $slow = new DummyClient(
            $this->makeChainInfo('s', 's', $pubHex),
            [
                'round' => 2,
                'randomness' => bin2hex(hash('sha256', $sig2, true)),
                'signature' => bin2hex($sig2)
            ],
            100
        );
        $client = DrandClient::createFastestNode([$slow, $fast], new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]), ['verificationMode' => VerificationMode::DISABLED], 1);
        self::assertEquals(1, $client->getBeacon(1)->getRound());
    }

    /**
     * Fuzz test for FastestNodeClient.
     *
     * @return void
     */
    public function testFastestNodeClientFuzz(): void
    {
        $clients = [];
        $latencies = [];
        for ($i = 0; $i < 5; $i++) {
            $delay = rand(1, 100); // ms
            $chainInfo = [
                'hash' => bin2hex(random_bytes(4)),
                'public_key' => bin2hex(random_bytes(96)),
                'period' => 30,
                'genesis_time' => 1000,
                'groupHash' => 'g',
                'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                'metadata' => ['beaconID' => Network::MAINNET->value],
            ];
            $beacon = [
                'round' => 1,
                'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
                'signature' => bin2hex(random_bytes(48)),
            ];
            $clients[] = new class ($chainInfo, $beacon, $delay) implements \Drand\Client\Http\HttpClientInterface {
                /**
                 * @var array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}}
                 */
                private array $chainInfo;
                /**
                 * @var array{round: int, randomness: string, signature: string}
                 */
                private array $beacon;
                private int $delay;
                /**
                 * @param array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}} $chainInfo
                 * @param array{round: int, randomness: string, signature: string} $beacon
                 * @param int $delay
                 */
                public function __construct(array $chainInfo, array $beacon, int $delay)
                {
                    $this->chainInfo = $chainInfo;
                    $this->beacon = $beacon;
                    $this->delay = $delay;
                }
                public function getChainInfo(): \Drand\Client\ValueObject\Chain
                {
                    usleep($this->delay * 1000);
                    return Chain::fromArray($this->chainInfo);
                }
                public function getBeacon(int $round): \Drand\Client\ValueObject\Beacon
                {
                    return \Drand\Client\ValueObject\Beacon::fromArray($this->beacon);
                }
                public function getLatestBeacon(): \Drand\Client\ValueObject\Beacon
                {
                    return \Drand\Client\ValueObject\Beacon::fromArray($this->beacon);
                }
            };
            $latencies[] = $delay;
        }
        $fastestIndex = array_keys($latencies, min($latencies), true);
        $fastest = new \Drand\Client\Http\FastestNodeClient($clients, 1);
        $beacon = $fastest->getBeacon(1);
        self::assertEquals(1, $beacon->getRound());
        // Simulate all nodes failing
        $failingClients = [];
        for ($i = 0; $i < 3; $i++) {
            $failingClients[] = new class implements \Drand\Client\Http\HttpClientInterface {
                public function getChainInfo(): \Drand\Client\ValueObject\Chain
                {
                    throw new \RuntimeException('fail');
                }
                public function getBeacon(int $round): \Drand\Client\ValueObject\Beacon
                {
                    throw new \RuntimeException('fail');
                }
                public function getLatestBeacon(): \Drand\Client\ValueObject\Beacon
                {
                    throw new \RuntimeException('fail');
                }
            };
        }
        $fastestFail = new \Drand\Client\Http\FastestNodeClient($failingClients, 1);
        $this->expectException(\RuntimeException::class);
        $fastestFail->getBeacon(1);
    }
}
