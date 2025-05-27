<?php

namespace Drand\Client\Backend;

use Drand\Client\Exception\VerificationUnavailableException;
use Drand\Client\Enum\SignatureScheme;

/**
 * Fallback backend that supports basic signature verification using PHP's GMP extension.
 *
 * This backend is intended for testing and development only. It provides a simplified BLS12-381 signature verification
 * using PHP's GMP extension, but does not perform real cryptographic checks. For production, use the FFI backend with libblst.
 *
 * @psalm-suppress UnusedClass
 */

/** @psalm-suppress UnusedClass */
class PhpGmpBackend implements VerifierBackendInterface
{
    /**
     * Check if this backend supports a given signature scheme.
     *
     * Currently only supports G2-based schemes.
     *
     * @param SignatureScheme $scheme
     * @return bool True if the scheme is supported
     */
    #[\Override]
    public function supportsScheme(SignatureScheme $scheme): bool
    {
        // Currently only supports G2 schemes
        return in_array($scheme, [
            SignatureScheme::PEDERSEN_BLS_CHAINED,
            SignatureScheme::PEDERSEN_BLS_UNCHAINED
        ], true);
    }

    /**
     * Basic BLS12-381 verification using GMP. This is a simplified implementation and should only be used for testing.
     * Production systems should use the FFI backend with libblst for secure and performant verification.
     *
     * @param string $signature
     * @param string $message
     * @param string $publicKey
     * @param SignatureScheme $scheme
     * @return bool True if signature is valid
     * @throws \InvalidArgumentException If the scheme is not supported
     * @throws VerificationUnavailableException If GMP extension is not loaded
     */
    #[\Override]
    public function verify(string $signature, string $message, string $publicKey, SignatureScheme $scheme): bool
    {
        if (!$this->supportsScheme($scheme)) {
            throw new \InvalidArgumentException("Unsupported signature scheme: $scheme->value");
        }

        if (!extension_loaded('gmp')) {
            throw new VerificationUnavailableException(
                'Signature verification unavailable: GMP extension not loaded'
            );
        }

        // Input validation: throw if any input is empty
        if ($signature === '' || $message === '' || $publicKey === '') {
            throw new \InvalidArgumentException('Signature, message, and publicKey must not be empty');
        }

        // Convert binary inputs to GMP numbers
        $sig = gmp_import($signature);
        $msg = gmp_import($message);
        $pub = gmp_import($publicKey);

        // Perform basic modular arithmetic check (NOT secure, just a placeholder)
        // Real BLS verification requires proper curve operations
        return gmp_cmp(
            gmp_mod(
                gmp_mul($sig, $pub),
                gmp_init('0x73eda753299d7d483339d80809a1d80553bda402fffe5bfeffffffff00000001', 16)
            ),
            gmp_mod($msg, gmp_init('0x73eda753299d7d483339d80809a1d80553bda402fffe5bfeffffffff00000001', 16))
        ) === 0;
    }
}
