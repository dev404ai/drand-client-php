<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Drand\Client\DrandClient;
use Drand\Client\ValueObject\Chain;
use Drand\Client\Enum\SignatureScheme;
use Tests\ValueObject\Network;
use Drand\Client\Enum\VerificationMode;
use Drand\Client\Enum\CachePolicy;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\Backend\PhpGmpBackend;
use Drand\Client\Exception\VerificationUnavailableException;
use Drand\Client\Http\MultiChainClient;
use Drand\Client\Http\HttpClientInterface;
use Drand\Client\Http\FastestNodeClient;
use Tests\ValueObject\DummyClient;

/**
 * @group integration
 * @covers \Drand\Client\DrandClient
 * @covers \Drand\Client\ValueObject\Chain
 */
final class DrandClientIntegrationTest extends TestCase
{
    /**
     * Helper to create chain info arrays for tests.
     *
     * @param string $hash
     * @param string $publicKey
     * @param int $period
     * @param int $genesisTime
     * @param \Drand\Client\Enum\SignatureScheme $scheme
     * @param array{beaconID: string} $metadata
     * @return array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}}
     */
    private function makeChainInfo(string $hash, string $publicKey, int $period, int $genesisTime, SignatureScheme $scheme, array $metadata = ['beaconID' => Network::MAINNET->value]): array
    {
        return [
            'hash' => $hash,
            'public_key' => $publicKey,
            'period' => $period,
            'genesis_time' => $genesisTime,
            'groupHash' => 'g',
            'schemeID' => $scheme->value,
            'metadata' => $metadata,
        ];
    }

    /**
     * Test roundAt returns correct round for given time.
     *
     * @return void
     */
    public function testGetBeaconByTime(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $sig = random_bytes(48);
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 42,
            'randomness' => bin2hex(hash('sha256', $sig, true)),
            'signature' => bin2hex($sig)
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $round = $drand->roundAt(1000 + 30 * 41); // there should be 42nd round
        self::assertEquals(42, $round);
        self::assertEquals(42, $drand->getBeacon($round)->round);
    }

    /**
     * Test roundTime returns correct time for given round.
     *
     * @return void
     */
    public function testGetRoundTime(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $round = 100;
        $expectedTime = 1000 + ($round - 1) * 30;
        self::assertEquals($expectedTime, $drand->roundTime($round));
    }

    /**
     * Test getChain returns correct chain info and scheme.
     *
     * @return void
     */
    public function testPrintChainInfoAndScheme(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $chain = $drand->getChain();
        self::assertEquals('h', $chain->hash);
        self::assertEquals(SignatureScheme::PEDERSEN_BLS_CHAINED->value, $chain->schemeID->value);
    }

    /**
     * Test roundAt throws exception if time is before genesis.
     *
     * @return void
     */
    public function testRoundAtThrowsIfBeforeGenesis(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $this->expectException(\InvalidArgumentException::class);
        $drand->roundAt(999); // genesis is 1000
    }

    /**
     * Test roundTime throws exception if round is less than one.
     *
     * @return void
     */
    public function testRoundTimeThrowsIfRoundLessThanOne(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $this->expectException(\InvalidArgumentException::class);
        $drand->roundTime(0);
    }

    /**
     * Test getBeacon throws exception on invalid signature.
     *
     * @return void
     */
    public function testGetBeaconThrowsOnInvalidSignature(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => '00',
            'signature' => '00'
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::ENABLED
            ]
        );
        $this->expectException(\Drand\Client\Exception\VerificationUnavailableException::class);
        $drand->getBeacon(1);
        self::fail('Expected VerificationUnavailableException was not thrown');
    }

    /**
     * Test disabling beacon verification option.
     *
     * @return void
     */
    public function testDisableBeaconVerificationOption(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $sig = random_bytes(48);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', $sig, true)),
            'signature' => bin2hex($sig)
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        self::assertEquals(1, $drand->getBeacon(1)->round);
    }

    /**
     * Test getChain caching behavior.
     *
     * @return void
     */
    public function testGetChainCaching(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        /**
         * @var array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}} $chainInfo
         * @var array{round: int, randomness: string, signature: string} $beacon
         */
        $calls = 0;
        $client = new class ($chainInfo, $beacon, $calls) implements \Drand\Client\Http\HttpClientInterface {
            /**
             * @var array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}}
             */
            private array $chainInfo;
            /**
             * @var array{round: int, randomness: string, signature: string}
             */
            private array $beacon;
            /**
             * @var int
             */
            private $calls;
            /**
             * @param array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}} $chainInfo
             * @param array{round: int, randomness: string, signature: string} $beacon
             * @param int $calls
             */
            public function __construct(array $chainInfo, array $beacon, &$calls)
            {
                $this->chainInfo = $chainInfo;
                $this->beacon = $beacon;
                $this->calls = &$calls;
            }
            public function getChainInfo(): \Drand\Client\ValueObject\Chain
            {
                $this->calls++;
                return \Drand\Client\ValueObject\Chain::fromArray($this->chainInfo);
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
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $drand->getChain();
        $drand->getChain(); // Should be cached
        self::assertEquals(1, $calls);
    }

    /**
     * Test MultiChainClient failover logic.
     *
     * @return void
     */
    public function testMultiChainFailover(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $sig1 = random_bytes(48);
        $sig2 = random_bytes(48);
        $mainnet = new DummyClient(
            $this->makeChainInfo('main', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED),
            [
                'round' => 1,
                'randomness' => bin2hex(hash('sha256', $sig1, true)),
                'signature' => bin2hex($sig1)
            ]
        );
        $testnet = new DummyClient(
            $this->makeChainInfo('test', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED),
            [
                'round' => 2,
                'randomness' => bin2hex(hash('sha256', $sig2, true)),
                'signature' => bin2hex($sig2)
            ]
        );
        $multi = new \Drand\Client\Http\MultiChainClient([$mainnet, $testnet]);
        self::assertEquals('main', $multi->getChainInfo()->hash);
        // Simulate failover by switching to testnet
        self::assertEquals('test', $multi->forChainHash('test')->getChainInfo()->hash);
    }

    /**
     * Fuzz test for roundAt method.
     *
     * @return void
     */
    public function testRoundAtFuzz(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        $genesis = 1000;
        $period = 30;
        for ($i = 0; $i < 20; $i++) {
            $time = $genesis + $period * rand(0, 1000);
            $round = $drand->roundAt($time);
            // @phpstan-ignore-next-line
            self::assertIsInt($round);
        }
        // Invalid times
        for ($i = 0; $i < 5; $i++) {
            $time = rand(0, $genesis - 1);
            try {
                $drand->roundAt($time);
                self::fail('Expected exception for time before genesis');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('genesis', $e->getMessage());
            }
        }
    }

    /**
     * Fuzz test for roundTime method.
     *
     * @return void
     */
    public function testRoundTimeFuzz(): void
    {
        $pubHex = bin2hex(random_bytes(96));
        $chainInfo = $this->makeChainInfo('h', $pubHex, 30, 1000, SignatureScheme::PEDERSEN_BLS_CHAINED);
        $beacon = [
            'round' => 1,
            'randomness' => bin2hex(hash('sha256', random_bytes(48), true)),
            'signature' => bin2hex(random_bytes(48)),
        ];
        $client = new DummyClient($chainInfo, $beacon);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier([
                'gmp' => new \Drand\Client\Backend\PhpGmpBackend()
            ]),
            [
                'verificationMode' => VerificationMode::DISABLED
            ]
        );
        for ($i = 0; $i < 20; $i++) {
            $round = rand(1, 1000);
            $time = $drand->roundTime($round);
            // @phpstan-ignore-next-line
            self::assertIsInt($time);
        }
        // Invalid rounds
        for ($i = 0; $i < 5; $i++) {
            $round = rand(-100, 0);
            try {
                $drand->roundTime($round);
                self::fail('Expected exception for round < 1');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('Round number', $e->getMessage());
            }
        }
    }

    /**
     * Fuzz test for Chain and Beacon value objects.
     *
     * @return void
     */
    public function testChainAndBeaconFuzz(): void
    {
        // Fuzz Chain
        for ($i = 0; $i < 10; $i++) {
            $data = [
                'hash' => bin2hex(random_bytes(rand(1, 64))),
                'public_key' => bin2hex(random_bytes(rand(1, 96))),
                'period' => rand(1, 100),
                'genesis_time' => rand(0, 100000),
                'groupHash' => bin2hex(random_bytes(rand(1, 32))),
                'schemeID' => 'pedersen-bls-chained',
                'metadata' => ['beaconID' => Network::MAINNET->value],
            ];
            $chain = \Drand\Client\ValueObject\Chain::fromArray($data);
            // @phpstan-ignore-next-line
            self::assertInstanceOf(\Drand\Client\ValueObject\Chain::class, $chain);
        }
        // Fuzz Beacon
        for ($i = 0; $i < 10; $i++) {
            $data = [
                'round' => rand(1, 100000),
                'randomness' => bin2hex(random_bytes(rand(1, 32))),
                'signature' => bin2hex(random_bytes(rand(1, 48))),
            ];
            $beacon = \Drand\Client\ValueObject\Beacon::fromArray($data);
            // @phpstan-ignore-next-line
            self::assertInstanceOf(\Drand\Client\ValueObject\Beacon::class, $beacon);
        }
    }
}
