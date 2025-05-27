<?php

declare(strict_types=1);

namespace Drand\Client\ValueObject;

use Drand\Client\Enum\SignatureScheme;

/**
 * Value object representing drand chain info.
 *
 * Encapsulates all chain parameters and provides helpers for supported schemes and serialization.
 */
readonly final class Chain
{
    /**
     * Construct a Chain value object.
     *
     * @param string $hash Chain hash
     * @param string $publicKey Public key in hex format
     * @param int $period Seconds between randomness rounds
     * @param int $genesisTime Unix timestamp when the group began
     * @param string $groupHash Hash of the group file
     * @param SignatureScheme $schemeID Signature scheme identifier (enum)
     * @param array{beaconID: string} $metadata Chain metadata
     * @throws \InvalidArgumentException If the signature scheme is not supported
     */
    public function __construct(
        public string $hash,
        public string $publicKey,
        public int $period,
        public int $genesisTime,
        public string $groupHash,
        public SignatureScheme $schemeID,
        public array $metadata = ['beaconID' => 'default']
    ) {
        // Validate scheme
        if (!in_array($schemeID, self::getSupportedSchemes(), true)) {
            throw new \InvalidArgumentException(
                "Unsupported signature scheme: {$schemeID->value}"
            );
        }
    }

    /**
     * Get the public key for this chain.
     *
     * @return string
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Get the chain hash.
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get the period (seconds between rounds).
     *
     * @return int
     */
    public function getPeriod(): int
    {
        return $this->period;
    }

    /**
     * Get the genesis time (Unix timestamp).
     *
     * @return int
     */
    public function getGenesisTime(): int
    {
        return $this->genesisTime;
    }

    /**
     * Get the signature scheme identifier for this chain.
     *
     * @return SignatureScheme
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getSchemeID(): SignatureScheme
    {
        return $this->schemeID;
    }

    /**
     * Get the chain metadata.
     *
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a Chain instance from an API response array.
     *
     * @param array{
     *     hash: string,
     *     public_key: string,
     *     period: int,
     *     genesis_time: int,
     *     groupHash: string,
     *     schemeID: string,
     *     metadata: array{beaconID: string}
     * } $data
     * @return static
     * @throws \InvalidArgumentException If required keys are missing or scheme is invalid
     */
    public static function fromArray(array $data): static
    {
        foreach (
            [
            'hash',
            'public_key',
            'period',
            'genesis_time',
            'groupHash',
            'schemeID',
            'metadata',
            ] as $key
        ) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(
                    "Missing required chain info key: '$key'"
                );
            }
        }
        $schemeID = $data['schemeID'];
        $schemeID = SignatureScheme::fromString($schemeID);
        return new static(
            $data['hash'],
            $data['public_key'],
            $data['period'],
            $data['genesis_time'],
            $data['groupHash'],
            $schemeID,
            $data['metadata']
        );
    }

    /**
     * Get all supported signature schemes.
     *
     * @return array<int, SignatureScheme>
     */
    public static function getSupportedSchemes(): array
    {
        return [
            SignatureScheme::PEDERSEN_BLS_CHAINED,
            SignatureScheme::PEDERSEN_BLS_UNCHAINED,
            SignatureScheme::BLS_UNCHAINED_G1,
            SignatureScheme::BLS_RFC9380_G1,
            SignatureScheme::BN254_ON_G1,
        ];
    }

    /**
     * Convert this Chain object to an array (for serialization or debugging).
     *
     * @return array{hash: string, public_key: string, period: int, genesis_time: int, groupHash: string, schemeID: string, metadata: array{beaconID: string}}
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'public_key' => $this->publicKey,
            'period' => $this->period,
            'genesis_time' => $this->genesisTime,
            'groupHash' => $this->groupHash,
            'schemeID' => $this->schemeID->value,
            'metadata' => $this->metadata,
        ];
    }
}
