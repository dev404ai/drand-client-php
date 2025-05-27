<?php

declare(strict_types=1);

namespace Drand\Client\Http;

use Drand\Client\ValueObject\Chain;
use Drand\Client\ValueObject\Beacon;

/**
 * Client that selects the fastest drand node from a list of endpoints.
 *
 * Measures latency to each node and delegates requests to the fastest one.
 */
final class FastestNodeClient implements HttpClientInterface
{
    /** @var array<int, HttpClientInterface> */
    private readonly array $clients;
    /** @var array<int, float> */
    private array $latencies = [];
    private int $lastUpdate = 0;
    private readonly int $updateInterval;
    private int $fastestIndex = 0;

    /**
     * Construct a FastestNodeClient instance.
     *
     * @param HttpClientInterface[] $clients
     * @param int $updateInterval How often to re-measure latency (seconds)
     * @throws \InvalidArgumentException If no clients are provided
     */
    public function __construct(array $clients, int $updateInterval = 300)
    {
        if (count($clients) < 1) {
            throw new \InvalidArgumentException('At least one client required');
        }
        $this->clients = array_values($clients);
        $this->updateInterval = $updateInterval;
        $this->measureLatencies();
    }

    /**
     * Measure latency to all nodes and select the fastest.
     */
    private function measureLatencies(): void
    {
        $this->latencies = [];
        foreach ($this->clients as $i => $client) {
            $start = microtime(true);
            try {
                $client->getChainInfo();
                $latency = microtime(true) - $start;
            } catch (\Throwable $e) {
                $latency = INF;
            }
            $this->latencies[$i] = $latency;
        }
        asort($this->latencies);
        $this->fastestIndex = (int)array_key_first($this->latencies);
        $this->lastUpdate = time();
    }

    /**
     * Get the currently fastest client, updating if needed.
     */
    private function getFastest(): HttpClientInterface
    {
        if (time() - $this->lastUpdate > $this->updateInterval) {
            $this->measureLatencies();
        }
        return $this->clients[$this->fastestIndex];
    }

    /**
     * Get chain info from the fastest node.
     *
     * @return Chain
     */
    #[\Override]
    public function getChainInfo(): Chain
    {
        return $this->getFastest()->getChainInfo();
    }

    /**
     * Get a beacon for a specific round from the fastest node.
     *
     * @param int $round
     * @return Beacon
     */
    #[\Override]
    public function getBeacon(int $round): Beacon
    {
        return $this->getFastest()->getBeacon($round);
    }

    /**
     * Get the latest beacon from the fastest node.
     *
     * @return Beacon
     */
    #[\Override]
    public function getLatestBeacon(): Beacon
    {
        return $this->getFastest()->getLatestBeacon();
    }
}
