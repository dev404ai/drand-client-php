<?php

namespace Drand\Client\Backend;

use Drand\Client\Enum\SignatureScheme;

/**
 * Interface for pluggable signature verification backends.
 *
 * Implementations of this interface provide support for verifying BLS signatures
 * using different cryptographic libraries or approaches. Backends should clearly document
 * which signature schemes they support and any security considerations.
 */

interface VerifierBackendInterface
{
    /**
     * Check if this backend supports a given signature scheme.
     *
     * @param SignatureScheme $scheme Signature scheme identifier (enum)
     * @return bool True if the scheme is supported
     */
    public function supportsScheme(SignatureScheme $scheme): bool;

    /**
     * Verify a signature.
     *
     * @param string $signature Binary signature data
     * @param string $message Binary message data
     * @param string $publicKey Binary public key data
     * @param SignatureScheme $scheme Signature scheme identifier (enum)
     * @return bool True if signature is valid
     * @throws \InvalidArgumentException If scheme is not supported
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function verify(string $signature, string $message, string $publicKey, SignatureScheme $scheme): bool;
}
