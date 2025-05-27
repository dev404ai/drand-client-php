<?php

declare(strict_types=1);

namespace Drand\Client\Http;

use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;

/**
 * Interface for making HTTP requests to drand nodes.
 */
interface HttpClientInterface
{
    /**
     * Get chain information from the node.
     *
     * @return Chain
     * @throws \RuntimeException If the request fails
     */
    public function getChainInfo(): Chain;

    /**
     * Get a beacon for a specific round.
     *
     * @param int $round The round number to fetch
     * @return Beacon
     * @throws \RuntimeException If the request fails
     */
    public function getBeacon(int $round): Beacon;

    /**
     * Get the latest beacon.
     *
     * @return Beacon
     * @throws \RuntimeException If the request fails
     */
    public function getLatestBeacon(): Beacon;
}
