<?php

declare(strict_types=1);

namespace Drand\Client;

use Drand\Client\Backend\VerifierBackendInterface;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\ValueObject\Chain;
use Drand\Client\Enum\SignatureScheme;
use Drand\Client\Exception\VerificationUnavailableException;

/**
 * High-level verifier that selects the fastest available backend for signature verification.
 *
 * This class attempts to verify drand beacons using the first backend that supports the required signature scheme.
 * It also provides a method to check that the randomness matches the signature.
 */
final class Verifier
{
    /**
     * @var array<string, VerifierBackendInterface> Backends for signature verification
     */
    private readonly array $backends;

    /**
     * Construct a Verifier instance.
     *
     * @param array<string, VerifierBackendInterface> $backends
     * @throws \InvalidArgumentException If no backends are provided
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct(array $backends)
    {
        if ($backends === []) {
            throw new \InvalidArgumentException('At least one verification backend must be provided');
        }
        $this->backends = $backends;
    }

    /**
     * Safely convert a hex string to binary, validating input.
     *
     * @param string $hex Hexadecimal string
     * @param string $context Context for error messages
     * @return string Binary string
     * @throws \InvalidArgumentException If input is not valid hex
     */
    private function safeHex2bin(string $hex, string $context = 'input'): string
    {
        if (strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            throw new \InvalidArgumentException(
                "Invalid hex input for $context: '$hex'"
            );
        }
        $bin = hex2bin($hex);
        if ($bin === false) {
            throw new \InvalidArgumentException(
                "hex2bin failed for $context: '$hex'"
            );
        }
        return $bin;
    }

    /**
     * Verify a drand beacon signature using the appropriate backend and scheme.
     *
     * @param Beacon $beacon The beacon to verify
     * @param Chain $chain The chain info containing the public key and scheme
     * @return bool True if the signature is valid
     * @throws VerificationUnavailableException If no backend supports the scheme
     * @throws \InvalidArgumentException If input data is invalid
     */
    public function verify(Beacon $beacon, Chain $chain): bool
    {
        $scheme = $chain->schemeID;
        // Find a backend that supports this scheme
        $backend = null;
        foreach ($this->backends as $b) {
            if ($b->supportsScheme($scheme)) {
                $backend = $b;
                break;
            }
        }

        if (!$backend instanceof VerifierBackendInterface) {
            throw new VerificationUnavailableException(
                "No verification backend available for scheme: {$scheme->value}"
            );
        }

        // Convert hex to binary
        $sig = $this->safeHex2bin($beacon->signature, 'signature');
        $pub = $this->safeHex2bin($chain->publicKey, 'public key');

        // Auto-detect swapped G1 layout for testnets/legacy
        if ($scheme === SignatureScheme::PEDERSEN_BLS_CHAINED && strlen($pub) === 48 && strlen($sig) === 96) {
            $scheme = SignatureScheme::BLS_UNCHAINED_G1;
        }

        // Get message based on scheme type
        $msg = $scheme->isChained() && $beacon->previousSignature !== null
            ? $this->safeHex2bin($beacon->previousSignature, 'previous signature')
            : pack('J', $beacon->round);

        // Verify signature
        return $backend->verify($sig, $msg, $pub, $scheme);
    }

    /**
     * Check if the randomness value matches the beacon signature (SHA-256).
     *
     * @param Beacon $beacon The beacon to check
     * @return bool True if randomness matches the signature
     */
    public function verifyRandomness(Beacon $beacon): bool
    {
        $sig = $this->safeHex2bin($beacon->signature, 'signature');
        $randomness = $this->safeHex2bin($beacon->randomness, 'randomness');

        return hash('sha256', $sig, true) === $randomness;
    }
}
