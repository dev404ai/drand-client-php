<?php

declare(strict_types=1);

namespace Tests\ValueObject;

use Drand\Client\Http\HttpClientInterface;
use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;

/**
 * Dummy HTTP client for testing, returns fixed chain info and beacon data.
 */
final class DummyClient implements HttpClientInterface
{
    /**
     * @var array{
     *     public_key: string,
     *     period: int,
     *     genesis_time: int,
     *     hash: string,
     *     groupHash: string,
     *     schemeID: string,
     *     metadata: array{beaconID: string}
     * }
     */
    public readonly array $chainInfo;
    /**
     * @var array{round: int, randomness: string, signature: string, previous_signature?: string|null}
     */
    public readonly array $beacon;
    public readonly int $delay;

    /**
     * @param array{
     *     public_key: string,
     *     period: int,
     *     genesis_time: int,
     *     hash: string,
     *     groupHash: string,
     *     schemeID: string,
     *     metadata: array{beaconID: string}
     * } $chainInfo
     * @param array{
     *     round: int,
     *     randomness: string,
     *     signature: string,
     *     previous_signature?: string|null
     * } $beacon
     * @param int $delay
     */
    public function __construct(
        array $chainInfo,
        array $beacon,
        int $delay = 0
    ) {
        $this->chainInfo = $chainInfo;
        $this->beacon = $beacon;
        $this->delay = $delay;
    }

    /**
     * Get chain info as a Chain value object (with optional delay).
     *
     * @return Chain
     */
    public function getChainInfo(): Chain
    {
        usleep($this->delay * 1000);
        return Chain::fromArray($this->chainInfo);
    }

    /**
     * Get a beacon for a specific round (with optional delay).
     *
     * @param int $round
     * @return Beacon
     */
    public function getBeacon(int $round): Beacon
    {
        usleep($this->delay * 1000);
        return Beacon::fromArray($this->beacon);
    }

    /**
     * Get the latest beacon (with optional delay).
     *
     * @return Beacon
     */
    public function getLatestBeacon(): Beacon
    {
        usleep($this->delay * 1000);
        return Beacon::fromArray($this->beacon);
    }
}
