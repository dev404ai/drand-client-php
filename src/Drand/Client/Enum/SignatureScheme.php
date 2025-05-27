<?php

declare(strict_types=1);

namespace Drand\Client\Enum;

/**
 * Supported drand signature schemes as a PHP 8.2+ enum.
 */
enum SignatureScheme: string
{
    case PEDERSEN_BLS_CHAINED = 'pedersen-bls-chained';
    case PEDERSEN_BLS_UNCHAINED = 'pedersen-bls-unchained';
    case BLS_UNCHAINED_G1 = 'bls-unchained-on-g1';
    case BLS_RFC9380_G1 = 'bls-unchained-g1-rfc9380';
    case BN254_ON_G1 = 'bn254-on-g1';

    /**
     * Get the domain separation tag (DST) for the current signature scheme.
     *
     * @return string Domain separation tag used for signature verification
     */
    public function getDST(): string
    {
        return match ($this) {
            self::PEDERSEN_BLS_CHAINED,
            self::PEDERSEN_BLS_UNCHAINED => 'BLS_SIG_BLS12381G2_XMD:SHA-256_SSWU_RO_NUL_',
            self::BLS_UNCHAINED_G1 =>
                // Invalid but kept for backwards compatibility
                'BLS_SIG_BLS12381G2_XMD:SHA-256_SSWU_RO_NUL_',
            self::BLS_RFC9380_G1 => 'BLS_SIG_BLS12381G1_XMD:SHA-256_SSWU_RO_NUL_',
            self::BN254_ON_G1 => 'BLS_SIG_BN254G1_XMD:KECCAK-256_SVDW_RO_NUL_',
        };
    }

    /**
     * Check if the current scheme uses G1 for signatures.
     *
     * @return bool True if the scheme uses G1, false otherwise
     */
    public function isG1Scheme(): bool
    {
        return match ($this) {
            self::BLS_UNCHAINED_G1, self::BLS_RFC9380_G1, self::BN254_ON_G1 => true,
            default => false,
        };
    }

    /**
     * Check if the current scheme is chained (uses previous signature).
     *
     * @return bool True if the scheme is chained, false otherwise
     */
    public function isChained(): bool
    {
        return $this === self::PEDERSEN_BLS_CHAINED;
    }

    /**
     * Create enum from string value, throws if invalid.
     *
     * @param string $value String value of the signature scheme
     * @return self
     * @throws \ValueError If the value is not a valid signature scheme
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
