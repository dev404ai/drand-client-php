<?php

declare(strict_types=1);

namespace Drand\Client\ValueObject;

/**
 * Value object representing a drand beacon.
 *
 * Encapsulates beacon round, randomness, signature, and previous signature (if chained).
 */
readonly final class Beacon
{
    /**
     * Construct a Beacon value object.
     *
     * @param int $round Beacon round number
     * @param string $randomness Randomness value (hex)
     * @param string $signature Signature value (hex)
     * @param string|null $previousSignature Previous signature (hex), if chained
     */
    public function __construct(
        public int $round,
        public string $randomness,
        public string $signature,
        public ?string $previousSignature = null
    ) {
    }

    /**
     * Create a Beacon instance from an associative array.
     *
     * @param array{round: int|string, randomness: string, signature: string, previous_signature?: string|null} $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            (int)$data['round'],
            $data['randomness'],
            $data['signature'],
            $data['previous_signature'] ?? null
        );
    }

    /**
     * Get the randomness value for this beacon.
     *
     * @return string
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getRandomness(): string
    {
        return $this->randomness;
    }

    /**
     * Get the signature value for this beacon.
     *
     * @return string
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Get the previous signature for this beacon (if chained).
     *
     * @return string|null
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getPreviousSignature(): ?string
    {
        return $this->previousSignature;
    }

    /**
     * Returns the message to be signed/verified for this beacon (8-byte big-endian round number).
     *
     * @return string
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getRoundMessage(): string
    {
        // drand spec: round number as 8-byte big-endian unsigned integer
        return pack('J', $this->round);
    }

    /**
     * Get the round number for this beacon.
     *
     * @return int
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getRound(): int
    {
        return $this->round;
    }

    /**
     * Convert Beacon object to array (for tests and serialization).
     * @return array{round: int, randomness: string, signature: string, previous_signature?: string|null}
     * @phpstan-return array{round: int, randomness: string, signature: string, previous_signature?: string|null}
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function toArray(): array
    {
        $arr = [
            'round' => $this->round,
            'randomness' => $this->randomness,
            'signature' => $this->signature,
        ];
        if ($this->previousSignature !== null) {
            $arr['previous_signature'] = $this->previousSignature;
        }
        return $arr;
    }
}
