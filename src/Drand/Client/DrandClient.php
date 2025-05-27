<?php

declare(strict_types=1);

namespace Drand\Client;

use Drand\Client\Backend\VerifierBackendInterface;
use Drand\Client\Backend\PhpGmpBackend;
use Drand\Client\Backend\FFIBlstBackend;
use Drand\Client\Http\HttpClientInterface;
use Drand\Client\Http\CachingHttpClient;
use Drand\Client\Http\MultiChainClient;
use Drand\Client\Http\FastestNodeClient;
use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\Enum\VerificationMode;
use Drand\Client\Enum\CachePolicy;
use Drand\Client\Enum\SignatureScheme;
use Drand\Client\Exception\VerificationUnavailableException;

/**
 * Main drand client implementation.
 *
 * Supports single node, multiple chains (MultiChainClient), and fastest node selection (FastestNodeClient).
 * Provides methods to fetch chain info, beacons, and calculate round numbers and times.
 */
final class DrandClient
{
    /**
     * @var Chain|null Cached chain info
     */
    private ?Chain $chain = null;
    private readonly Verifier $verifier;
    private readonly HttpClientInterface $httpClient;
    /** @var array<string, mixed> */
    private readonly array $options;

    /**
     * Construct a DrandClient instance.
     *
     * @param HttpClientInterface $httpClient The HTTP client for making API requests
     * @param Verifier $verifier The verifier instance to use
     * @param array<string, mixed> $options Client options
     */
    public function __construct(
        HttpClientInterface $httpClient,
        Verifier $verifier,
        array $options = []
    ) {
        $this->verifier = $verifier;
        $this->httpClient = $httpClient;
        // Normalize options to enums
        $this->options = [
            'verificationMode' => isset($options['verificationMode'])
                ? (is_bool($options['verificationMode'])
                    ? VerificationMode::fromBool(!$options['verificationMode'])
                    : $options['verificationMode'])
                : VerificationMode::ENABLED,
            'cachePolicy' => isset($options['cachePolicy'])
                ? (is_bool($options['cachePolicy'])
                    ? CachePolicy::fromBool(!$options['cachePolicy'])
                    : $options['cachePolicy'])
                : CachePolicy::ENABLED,
            'chainVerificationParams' => $options['chainVerificationParams'] ?? null,
        ];
    }

    /**
     * Get information about the drand chain (from the default/selected client).
     *
     * @return Chain
     */
    public function getChain(): Chain
    {
        if ($this->chain === null) {
            $this->chain = $this->httpClient->getChainInfo();
        }
        return $this->chain;
    }

    /**
     * Get a beacon for a specific round. Optionally verifies the beacon signature and randomness.
     *
     * @param int $round
     * @return Beacon
     * @throws VerificationUnavailableException If the beacon is invalid or verification fails
     */
    /** @psalm-suppress PossiblyUnusedMethod */
    public function getBeacon(int $round): Beacon
    {
        $beacon = $this->httpClient->getBeacon($round);
        if ($this->options['verificationMode'] === VerificationMode::ENABLED) {
            $chain = $this->getChain();
            if (!$this->verifier->verify($beacon, $chain) || !$this->verifier->verifyRandomness($beacon)) {
                throw new VerificationUnavailableException('Invalid beacon signature or randomness');
            }
        }
        return $beacon;
    }

    /**
     * Get the latest beacon. Optionally verifies the beacon signature and randomness.
     *
     * @return Beacon
     * @throws VerificationUnavailableException If the beacon is invalid or verification fails
     */
    /** @psalm-suppress PossiblyUnusedMethod */
    public function getLatestBeacon(): Beacon
    {
        $beacon = $this->httpClient->getLatestBeacon();
        if ($this->options['verificationMode'] === VerificationMode::ENABLED) {
            $chain = $this->getChain();
            if (!$this->verifier->verify($beacon, $chain) || !$this->verifier->verifyRandomness($beacon)) {
                throw new VerificationUnavailableException('Invalid beacon signature or randomness');
            }
        }
        return $beacon;
    }

    /**
     * Get the round number for a given time (or now).
     *
     * @param int|null $time Unix timestamp, or null for current time
     * @return int Round number
     * @throws \InvalidArgumentException If time is before chain genesis
     */
    /** @psalm-suppress PossiblyUnusedMethod */
    public function roundAt(?int $time = null): int
    {
        $chain = $this->getChain();
        $time = $time ?? time();
        $genesis = $chain->getGenesisTime();
        $period = $chain->getPeriod();
        if ($time < $genesis) {
            throw new \InvalidArgumentException('Time is before chain genesis');
        }
        return (int)floor(($time - $genesis) / $period) + 1;
    }

    /**
     * Get the time when a round will be available.
     *
     * @param int $round
     * @return int Unix timestamp when the round will be available
     * @throws \InvalidArgumentException If round number is less than 1
     */
    /** @psalm-suppress PossiblyUnusedMethod */
    public function roundTime(int $round): int
    {
        $chain = $this->getChain();
        if ($round < 1) {
            throw new \InvalidArgumentException('Round number must be greater than 0');
        }
        return $chain->getGenesisTime() + ($round - 1) * $chain->getPeriod();
    }

    /**
     * Create a DrandClient instance for multiple chains.
     *
     * @param array<int, HttpClientInterface> $clients Array of HTTP clients, indexed by integer
     * @param Verifier $verifier The verifier instance to use
     * @param array<string, mixed> $options Client options
     * @return static
     * 
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function createMultiChain(array $clients, Verifier $verifier, array $options = []): static
    {
        return new static(new MultiChainClient($clients), $verifier, $options);
    }

    /**
     * Create a DrandClient instance that selects the fastest node.
     *
     * @param array<int, HttpClientInterface> $clients Array of HTTP clients, indexed by integer
     * @param Verifier $verifier The verifier instance to use
     * @param array<string, mixed> $options Client options
     * @param int $updateInterval Update interval in seconds
     * @return static
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function createFastestNode(
        array $clients,
        Verifier $verifier,
        array $options = [],
        int $updateInterval = 300
    ): static {
        return new static(
            new FastestNodeClient($clients, $updateInterval),
            $verifier,
            $options
        );
    }
}
