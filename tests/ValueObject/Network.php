<?php

declare(strict_types=1);

namespace Tests\ValueObject;

/**
 * Enum for drand networks (mainnet, testnet, devnet).
 * Используется только в тестах.
 */
enum Network: string
{
    case MAINNET = 'mainnet';
    case TESTNET = 'testnet';
    case DEVNET = 'devnet';

    /**
     * Get the default URL for the current network.
     *
     * @return string
     */
    public function getDefaultUrl(): string
    {
        return match ($this) {
            self::MAINNET => 'https://api.drand.sh',
            self::TESTNET => 'https://api.testnet.drand.sh',
            self::DEVNET => 'https://api.devnet.drand.sh',
        };
    }

    /**
     * Get the beacon ID for the current network.
     *
     * @return string
     */
    public function getBeaconID(): string
    {
        return $this->value;
    }
}
