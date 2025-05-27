<?php

declare(strict_types=1);

namespace Drand\Client\Http;

use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;

/**
 * Client for managing multiple drand chains.
 *
 * Allows selection of chain by hash or beaconID, and delegates requests to the appropriate client.
 */
final class MultiChainClient implements HttpClientInterface
{
    /** @var array<string, HttpClientInterface> */
    private readonly array $clientsByHash;
    /** @var array<string, HttpClientInterface> */
    private readonly array $clientsByBeaconID;
    private readonly HttpClientInterface $defaultClient;

    /**
     * Construct a MultiChainClient instance.
     *
     * @param HttpClientInterface[] $clients Array of clients for different chains
     * @throws \InvalidArgumentException if no clients provided
     */
    public function __construct(array $clients)
    {
        if ($clients === []) {
            throw new \InvalidArgumentException('At least one client must be provided');
        }
        $byHash = [];
        $byBeaconID = [];
        foreach ($clients as $client) {
            $chain = $client->getChainInfo();
            $byHash[$chain->getHash()] = $client;
            $byBeaconID[$chain->getMetadata()['beaconID']] = $client;
        }
        $this->clientsByHash = $byHash;
        $this->clientsByBeaconID = $byBeaconID;
        $this->defaultClient = reset($clients);
    }

    /**
     * Use the default client (first in list).
     *
     * @return Chain
     */
    #[\Override]
    public function getChainInfo(): Chain
    {
        return $this->defaultClient->getChainInfo();
    }

    /**
     * Use the default client to get a beacon for a specific round.
     *
     * @param int $round
     * @return Beacon
     */
    #[\Override]
    public function getBeacon(int $round): Beacon
    {
        return $this->defaultClient->getBeacon($round);
    }

    /**
     * Use the default client to get the latest beacon.
     *
     * @return Beacon
     */
    #[\Override]
    public function getLatestBeacon(): Beacon
    {
        return $this->defaultClient->getLatestBeacon();
    }

    /**
     * Get the client for a specific chain hash.
     *
     * @param string $hash
     * @return HttpClientInterface
     * @throws \InvalidArgumentException If the hash is unknown
     */
    /** @psalm-suppress PossiblyUnusedMethod */
    public function forChainHash(string $hash): HttpClientInterface
    {
        if (!isset($this->clientsByHash[$hash])) {
            $this->throwUnknownChainHash($hash);
        }
        return $this->clientsByHash[$hash];
    }

    private function throwUnknownChainHash(string $hash): never
    {
        throw new \InvalidArgumentException(
            "Unknown chain hash: $hash"
        );
    }

    /**
     * Get the client for a specific beaconID.
     *
     * @param string $beaconID
     * @return HttpClientInterface
     * @throws \InvalidArgumentException If the beaconID is unknown
     */
    /** @psalm-suppress PossiblyUnusedMethod */
    public function forBeaconID(string $beaconID): HttpClientInterface
    {
        if (!isset($this->clientsByBeaconID[$beaconID])) {
            $this->throwUnknownBeaconID($beaconID);
        }
        return $this->clientsByBeaconID[$beaconID];
    }

    private function throwUnknownBeaconID(string $beaconID): never
    {
        throw new \InvalidArgumentException(
            "Unknown beaconID: $beaconID"
        );
    }
}
