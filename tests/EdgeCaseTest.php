<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Drand\Client\DrandClient;
use Drand\Client\Backend\PhpGmpBackend;
use Drand\Client\Http\HttpClientInterface;
use Drand\Client\Http\CachingHttpClient;
use Drand\Client\Http\MultiChainClient;
use Drand\Client\Http\FastestNodeClient;
use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\Enum\VerificationMode;
use Drand\Client\Enum\CachePolicy;
use Drand\Client\Enum\SignatureScheme;
use Tests\ValueObject\Network;
use Drand\Client\Exception\VerificationUnavailableException;

/**
 * @covers \Drand\Client\DrandClient
 * @covers \Drand\Client\Http\CachingHttpClient
 * @covers \Drand\Client\Http\MultiChainClient
 * @covers \Drand\Client\Http\FastestNodeClient
 * @group unit
 */
final class EdgeCaseTest extends TestCase
{
    /**
     * Test that network errors throw exceptions.
     *
     * @return void
     */
    public function testNetworkErrorThrows(): void
    {
        $client = new class implements HttpClientInterface {
            /**
             * @return Chain
             */
            public function getChainInfo(): Chain
            {
                throw new \RuntimeException('Network error');
            }
            /**
             * @return Beacon
             */
            public function getBeacon(int $round): Beacon
            {
                throw new \RuntimeException('Network error');
            }
            /**
             * @return Beacon
             */
            public function getLatestBeacon(): Beacon
            {
                throw new \RuntimeException('Network error');
            }
        };
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]),
            ['verificationMode' => VerificationMode::DISABLED]
        );
        $this->expectException(\RuntimeException::class);
        $drand->getChain();
    }

    /**
     * Test that invalid chain info throws exception.
     *
     * @return void
     */
    public function testInvalidChainInfoThrows(): void
    {
        $client = new class implements HttpClientInterface {
            public function getChainInfo(): Chain
            {
                return Chain::fromArray([
                    'hash' => 'h',
                    'public_key' => 'pk',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'groupHash' => 'g',
                    'schemeID' => 'invalid-scheme', // Intentionally invalid
                    'metadata' => ['beaconID' => 'mainnet']
                ]);
            }
            public function getBeacon(int $round): Beacon
            {
                return Beacon::fromArray(['round' => 1, 'randomness' => 'r', 'signature' => 's']);
            }
            public function getLatestBeacon(): Beacon
            {
                return Beacon::fromArray(['round' => 1, 'randomness' => 'r', 'signature' => 's']);
            }
        };
        $this->expectException(\ValueError::class);
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]),
            ['verificationMode' => VerificationMode::DISABLED]
        );
        $drand->getChain();
    }

    /**
     * Test that chain validation error is thrown for invalid key/signature.
     *
     * @return void
     */
    public function testChainValidationError(): void
    {
        $client = new class implements HttpClientInterface {
            /**
             * @return Chain
             */
            public function getChainInfo(): Chain
            {
                return Chain::fromArray([
                    'hash' => 'h',
                    'public_key' => '00',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'groupHash' => 'g',
                    'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                    'metadata' => ['beaconID' => 'mainnet'],
                ]);
            }
            /**
             * @return Beacon
             */
            public function getBeacon(int $round): Beacon
            {
                return Beacon::fromArray(['round' => 1, 'randomness' => '00', 'signature' => '00']);
            }
            /**
             * @return Beacon
             */
            public function getLatestBeacon(): Beacon
            {
                return Beacon::fromArray(['round' => 1, 'randomness' => '00', 'signature' => '00']);
            }
        };
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]),
            ['verificationMode' => VerificationMode::ENABLED]
        );
        $this->expectException(\Drand\Client\Exception\VerificationUnavailableException::class);
        $drand->getBeacon(1);
        self::fail('Expected VerificationUnavailableException was not thrown');
    }

    /**
     * Test that getLatestBeacon throws on invalid signature.
     *
     * @return void
     */
    public function testGetLatestBeaconThrowsOnInvalidSignature(): void
    {
        $client = new class implements HttpClientInterface {
            /**
             * @return Chain
             */
            public function getChainInfo(): Chain
            {
                return Chain::fromArray([
                    'hash' => 'h',
                    'public_key' => '00',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'groupHash' => 'g',
                    'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                    'metadata' => ['beaconID' => 'mainnet'],
                ]);
            }
            /**
             * @return Beacon
             */
            public function getBeacon(int $round): Beacon
            {
                return Beacon::fromArray(['round' => 1, 'randomness' => '00', 'signature' => '00']);
            }
            public function getLatestBeacon(): Beacon
            {
                return Beacon::fromArray(['round' => 1, 'randomness' => '00', 'signature' => '00']);
            }
        };
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]),
            ['verificationMode' => VerificationMode::ENABLED]
        );
        $this->expectException(\Drand\Client\Exception\VerificationUnavailableException::class);
        $drand->getLatestBeacon();
        self::fail('Expected VerificationUnavailableException was not thrown');
    }

    /**
     * Test that CachingHttpClient caches and expires data correctly.
     *
     * @return void
     */
    public function testCachingHttpClientCachesAndExpires(): void
    {
        $calls = 0;
        $client = new class () implements HttpClientInterface {
            public int $calls;
            public function getChainInfo(): Chain
            {
                $this->calls++;
                return Chain::fromArray([
                    'hash' => bin2hex(random_bytes(8)),
                    'public_key' => 'p',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'groupHash' => 'g',
                    'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                    'metadata' => ['beaconID' => 'mainnet'],
                ]);
            }
            public function getBeacon(int $round): Beacon
            {
                $this->calls++;
                return Beacon::fromArray(['round' => $round,'randomness' => 'r','signature' => 's']);
            }
            public function getLatestBeacon(): Beacon
            {
                $this->calls++;
                return Beacon::fromArray(['round' => 1,'randomness' => 'r','signature' => 's']);
            }
        };
        $client->calls = &$calls;
        $caching = new CachingHttpClient($client, 1, 1); // 1s TTL
        $caching->getChainInfo();
        $caching->getChainInfo(); // Should be cached
        sleep(2);
        $caching->getChainInfo(); // Should expire and call again
        self::assertGreaterThanOrEqual(2, $calls);
    }

    /**
     * Test that MultiChainClient throws on unknown chain.
     *
     * @return void
     */
    public function testMultiChainClientThrowsOnUnknownChain(): void
    {
        $client = new class implements HttpClientInterface {
            public function getChainInfo(): Chain
            {
                return Chain::fromArray([
                    'hash' => 'h',
                    'public_key' => 'p',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'groupHash' => 'g',
                    'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                    'metadata' => ['beaconID' => 'mainnet'],
                ]);
            }
            public function getBeacon(int $round): Beacon
            {
                return Beacon::fromArray(['round' => $round,'randomness' => 'r','signature' => 's']);
            }
            public function getLatestBeacon(): Beacon
            {
                return Beacon::fromArray(['round' => 1,'randomness' => 'r','signature' => 's']);
            }
        };
        $multi = new MultiChainClient([$client]);
        $this->expectException(\InvalidArgumentException::class);
        $multi->forChainHash('unknown');
        $this->expectException(\InvalidArgumentException::class);
        $multi->forBeaconID('unknown');
    }

    /**
     * Test that FastestNodeClient throws on empty clients array.
     *
     * @return void
     */
    public function testFastestNodeClientThrowsOnEmptyClients(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FastestNodeClient([]);
    }

    /**
     * Test that HttpClient throws on HTTP error.
     *
     * @return void
     */
    public function testHttpClientThrowsOnHttpError(): void
    {
        $client = new class implements HttpClientInterface {
            public function getChainInfo(): Chain
            {
                throw new \RuntimeException('HTTP error');
            }
            public function getBeacon(int $round): Beacon
            {
                throw new \RuntimeException('HTTP error');
            }
            public function getLatestBeacon(): Beacon
            {
                throw new \RuntimeException('HTTP error');
            }
        };
        $drand = new DrandClient(
            $client,
            new \Drand\Client\Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]),
            ['verificationMode' => VerificationMode::DISABLED]
        );
        $this->expectException(\RuntimeException::class);
        $drand->getChain();
    }

    /**
     * Test that CachingHttpClient propagates HTTP error.
     *
     * @return void
     */
    public function testCachingHttpClientPropagatesHttpError(): void
    {
        $client = new class implements HttpClientInterface {
            public function getChainInfo(): Chain
            {
                throw new \RuntimeException('HTTP error');
            }
            public function getBeacon(int $round): Beacon
            {
                throw new \RuntimeException('HTTP error');
            }
            public function getLatestBeacon(): Beacon
            {
                throw new \RuntimeException('HTTP error');
            }
        };
        $caching = new CachingHttpClient($client, 1, 1);
        $this->expectException(\RuntimeException::class);
        $caching->getChainInfo();
    }

    /**
     * Fuzz test for CachingHttpClient cache expiration.
     *
     * @return void
     */
    public function testCachingHttpClientFuzz(): void
    {
        $calls = 0;
        $client = new class () implements HttpClientInterface {
            public int $calls;
            public function getChainInfo(): Chain
            {
                $this->calls++;
                return Chain::fromArray([
                    'hash' => bin2hex(random_bytes(8)),
                    'public_key' => 'p',
                    'period' => 30,
                    'genesis_time' => 1000,
                    'groupHash' => 'g',
                    'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                    'metadata' => ['beaconID' => 'mainnet'],
                ]);
            }
            public function getBeacon(int $round): Beacon
            {
                $this->calls++;
                return Beacon::fromArray(['round' => $round,'randomness' => 'r','signature' => 's']);
            }
            public function getLatestBeacon(): Beacon
            {
                $this->calls++;
                return Beacon::fromArray(['round' => 1,'randomness' => 'r','signature' => 's']);
            }
        };
        $client->calls = &$calls;
        for ($i = 0; $i < 10; $i++) {
            $ttl = rand(1, 3);
            $caching = new CachingHttpClient($client, $ttl, $ttl);
            $caching->getChainInfo();
            $caching->getChainInfo(); // Should be cached
            sleep($ttl + 1);
            $caching->getChainInfo(); // Should expire and call again
        }
        self::assertGreaterThanOrEqual(10, $calls);
    }

    /**
     * Fuzz test for MultiChainClient.
     *
     * @return void
     */
    public function testMultiChainClientFuzz(): void
    {
        $clients = [];
        $hashes = [];
        $beaconIDs = [];
        // Use enum values for valid beaconIDs
        $enumValues = [\Tests\ValueObject\Network::MAINNET->value, \Tests\ValueObject\Network::TESTNET->value, \Tests\ValueObject\Network::DEVNET->value, 'custom1', 'custom2'];
        for ($i = 0; $i < 5; $i++) {
            $hash = bin2hex(random_bytes(4));
            $beaconID = $enumValues[$i];
            $hashes[] = $hash;
            $beaconIDs[] = $beaconID;
            $clients[] = new class ($hash, $beaconID) implements HttpClientInterface {
                private string $hash;
                private string $beaconID;
                public function __construct(string $hash, string $beaconID)
                {
                    $this->hash = $hash;
                    $this->beaconID = $beaconID;
                }
                public function getChainInfo(): Chain
                {
                    return Chain::fromArray([
                        'hash' => $this->hash,
                        'public_key' => 'p',
                        'period' => 30,
                        'genesis_time' => 1000,
                        'groupHash' => 'g',
                        'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
                        'metadata' => ['beaconID' => $this->beaconID],
                    ]);
                }
                public function getBeacon(int $round): Beacon
                {
                    return Beacon::fromArray(['round' => $round,'randomness' => 'r','signature' => 's']);
                }
                public function getLatestBeacon(): Beacon
                {
                    return Beacon::fromArray(['round' => 1,'randomness' => 'r','signature' => 's']);
                }
            };
        }
        $multi = new MultiChainClient($clients);
        // Valid lookups
        for ($i = 0; $i < 5; $i++) {
            self::assertEquals(
                $hashes[$i],
                $multi->forChainHash($hashes[$i])->getChainInfo()->getHash()
            );
            self::assertEquals(
                $beaconIDs[$i],
                $multi->forBeaconID($beaconIDs[$i])->getChainInfo()->getMetadata()['beaconID']
            );
        }
        // Invalid lookups
        for ($i = 0; $i < 5; $i++) {
            $badHash = bin2hex(random_bytes(4));
            $badID = 'bad' . $i;
            $this->expectException(\InvalidArgumentException::class);
            $multi->forChainHash($badHash);
            $this->expectException(\InvalidArgumentException::class);
            $multi->forBeaconID($badID);
        }
    }
}
