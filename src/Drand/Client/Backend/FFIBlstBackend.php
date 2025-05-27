<?php

declare(strict_types=1);

namespace Drand\Client\Backend;

use Drand\Client\Enum\SignatureScheme;

/**
 * FFI backend that delegates signature verification to libblst.
 *
 * Supports both G1 and G2 signature schemes for drand randomness beacons.
 */

/** @psalm-suppress UnusedClass */
final class FFIBlstBackend implements VerifierBackendInterface
{
    private readonly \FFI $blst;

    /**
     * Construct an FFIBlstBackend instance.
     *
     * @param string $lib Path to the libblst shared library
     */
    public function __construct(string $lib = "libblst.so")
    {
        $cdef = <<<CDEF
        typedef unsigned char byte;
        typedef struct { byte b[48]; } blst_p1_aff;
        typedef struct { byte b[96]; } blst_p2_aff;
        typedef enum { BLST_SUCCESS = 0 } BLST_ERROR;
        
        // G2 signature verification (original drand)
        BLST_ERROR blst_core_verify_pk_in_g2(
            const blst_p2_aff *pk,
            const blst_p1_aff *sig,
            int hash_or_encode,
            const byte *msg, size_t msg_len,
            const byte *dst, size_t dst_len);
            
        // G1 signature verification (new schemes)
        BLST_ERROR blst_core_verify_pk_in_g1(
            const blst_p1_aff *pk,
            const blst_p2_aff *sig,
            int hash_or_encode,
            const byte *msg, size_t msg_len,
            const byte *dst, size_t dst_len);
        CDEF;

        $this->blst = \FFI::cdef($cdef, $lib);
    }

    /**
     * Check if this backend supports a given signature scheme.
     *
     * @param SignatureScheme $scheme
     * @return bool True if the scheme is supported
     */
    #[\Override]
    public function supportsScheme(SignatureScheme $scheme): bool
    {
        return in_array($scheme, [
            SignatureScheme::PEDERSEN_BLS_CHAINED,
            SignatureScheme::PEDERSEN_BLS_UNCHAINED,
            SignatureScheme::BLS_UNCHAINED_G1,
            SignatureScheme::BLS_RFC9380_G1
        ], true);
    }

    /**
     * Verify a signature using the appropriate BLS scheme via libblst FFI backend.
     * Supports both G1 and G2 signature schemes. Throws if the scheme is not supported or input sizes are invalid.
     *
     * @param string $signature Binary signature
     * @param string $message Binary message
     * @param string $publicKey Binary public key
     * @param SignatureScheme $scheme Signature scheme identifier
     * @return bool True if signature is valid
     * @throws \InvalidArgumentException If the scheme is not supported or input sizes are invalid
     */
    #[\Override]
    public function verify(string $signature, string $message, string $publicKey, SignatureScheme $scheme): bool
    {
        if (!$this->supportsScheme($scheme)) {
            throw new \InvalidArgumentException("Unsupported signature scheme: $scheme->value");
        }
        $isG1 = $scheme->isG1Scheme();
        $dst = $scheme->getDST();

        if ($isG1) {
            if (strlen($signature) !== 96 || strlen($publicKey) !== 48) {
                throw new \InvalidArgumentException(
                    "G1 scheme requires 96-byte signature and 48-byte pubkey, " .
                    "got sig=" . strlen($signature) . ", pub=" . strlen($publicKey)
                );
            }
            return $this->verifyG1($signature, $message, $publicKey, $dst);
        } else {
            if (strlen($signature) !== 48 || strlen($publicKey) !== 96) {
                throw new \InvalidArgumentException(
                    "G2 scheme requires 48-byte signature and 96-byte pubkey, " .
                    "got sig=" . strlen($signature) . ", pub=" . strlen($publicKey)
                );
            }
            return $this->verifyG2($signature, $message, $publicKey, $dst);
        }
    }

    /**
     * Verify a signature with public key in G2 (original drand scheme) using libblst FFI.
     *
     * @param string $signature
     * @param string $message
     * @param string $publicKey
     * @param string $dst
     * @return bool
     */
    private function verifyG2(string $signature, string $message, string $publicKey, string $dst): bool
    {
        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        $sig = $this->blst->new("blst_p1_aff");
        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        \FFI::memcpy($sig->b, $signature, 48);

        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        $pk = $this->blst->new("blst_p2_aff");
        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        \FFI::memcpy($pk->b, $publicKey, 96);

        // @phpstan-ignore-next-line: FFI dynamic method call is valid
        $err = $this->blst->blst_core_verify_pk_in_g2(
            $pk,
            $sig,
            1,
            $message,
            strlen($message),
            $dst,
            strlen($dst)
        );
        return $err === 0; // BLST_SUCCESS
    }

    /**
     * Verify a signature with public key in G1 (new schemes) using libblst FFI.
     *
     * @param string $signature
     * @param string $message
     * @param string $publicKey
     * @param string $dst
     * @return bool
     */
    private function verifyG1(string $signature, string $message, string $publicKey, string $dst): bool
    {
        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        $sig = $this->blst->new("blst_p2_aff");
        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        \FFI::memcpy($sig->b, $signature, 96);

        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        $pk = $this->blst->new("blst_p1_aff");
        // @phpstan-ignore-next-line: FFI dynamic property access is valid
        \FFI::memcpy($pk->b, $publicKey, 48);

        // @phpstan-ignore-next-line: FFI dynamic method call is valid
        $err = $this->blst->blst_core_verify_pk_in_g1(
            $pk,
            $sig,
            1,
            $message,
            strlen($message),
            $dst,
            strlen($dst)
        );
        return $err === 0; // BLST_SUCCESS
    }
}
