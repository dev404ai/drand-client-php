<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Drand\Client\Verifier;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\ValueObject\Chain;
use Drand\Client\Enum\SignatureScheme;
use Drand\Client\Exception\VerificationUnavailableException;
use Drand\Client\Backend\VerifierBackendInterface;
use Drand\Client\Backend\PhpGmpBackend;
use Drand\Client\Enum\SignatureScheme as EnumSignatureScheme;
use Drand\Client\Exception\VerificationUnavailableException as ExceptionVerificationUnavailableException;

/**
 * @group unit
 * @covers \Drand\Client\Verifier
 * @covers \Drand\Client\Enum\SignatureScheme
 */
final class VerifierAndSignatureSchemeTest extends TestCase
{
    /**
     * Test that verifyRandomness returns true for valid randomness.
     *
     * @return void
     */
    public function testVerifyRandomnessTrue(): void
    {
        $verifier = new Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]);
        $sig = random_bytes(48);
        $randomness = hash('sha256', $sig, true);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => bin2hex($randomness),
            'signature' => bin2hex($sig),
        ]);
        self::assertTrue($verifier->verifyRandomness($beacon));
    }

    /**
     * Test that verifyRandomness returns false for invalid randomness.
     *
     * @return void
     */
    public function testVerifyRandomnessFalse(): void
    {
        $verifier = new Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => 'ff',
            'signature' => '00',
        ]);
        self::assertFalse($verifier->verifyRandomness($beacon));
    }

    /**
     * Test that verify throws if no backend is available.
     *
     * @return void
     */
    public function testVerifyThrowsIfNoBackend(): void
    {
        $verifier = new Verifier(['gmp' => new \Drand\Client\Backend\PhpGmpBackend()]);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => str_repeat('00', 32),
            'signature' => str_repeat('00', 48),
        ]);
        $chain = Chain::fromArray([
            'public_key' => str_repeat('00', 96),
            'period' => 30,
            'genesis_time' => 1000,
            'hash' => 'h',
            'groupHash' => 'g',
            'schemeID' => SignatureScheme::BLS_UNCHAINED_G1->value,
            'beaconID' => 'mainnet',
            'metadata' => ['beaconID' => 'mainnet']
        ]);
        $this->expectException(\Drand\Client\Exception\VerificationUnavailableException::class);
        $verifier->verify($beacon, $chain);
    }

    /**
     * Test that getDST returns correct values for known schemes.
     *
     * @return void
     */
    public function testGetDSTReturnsCorrectValue(): void
    {
        self::assertEquals(
            'BLS_SIG_BLS12381G2_XMD:SHA-256_SSWU_RO_NUL_',
            SignatureScheme::PEDERSEN_BLS_CHAINED->getDST()
        );
        self::assertEquals(
            'BLS_SIG_BLS12381G1_XMD:SHA-256_SSWU_RO_NUL_',
            SignatureScheme::BLS_RFC9380_G1->getDST()
        );
    }

    /**
     * Test isG1Scheme helper.
     *
     * @return void
     */
    public function testIsG1Scheme(): void
    {
        self::assertTrue(SignatureScheme::BLS_UNCHAINED_G1->isG1Scheme());
        self::assertFalse(SignatureScheme::PEDERSEN_BLS_CHAINED->isG1Scheme());
    }

    /**
     * Test isChained helper.
     *
     * @return void
     */
    public function testIsChained(): void
    {
        self::assertTrue(SignatureScheme::PEDERSEN_BLS_CHAINED->isChained());
        self::assertFalse(SignatureScheme::PEDERSEN_BLS_UNCHAINED->isChained());
    }

    /**
     * Test getDST throws on unknown scheme.
     *
     * @return void
     */
    public function testGetDSTThrowsOnUnknownScheme(): void
    {
        $this->expectException(\ValueError::class);
        SignatureScheme::fromString('unknown-scheme')->getDST();
    }

    /**
     * Test verify with a mock backend that returns true.
     *
     * @return void
     */
    public function testVerifyWithMockBackendTrue(): void
    {
        $backend = new class implements VerifierBackendInterface {
            public function supportsScheme(\Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return true;
            }
            public function verify(string $signature, string $message, string $publicKey, \Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return true;
            }
        };
        $verifier = new Verifier(['mock' => $backend]);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => '00',
            'signature' => '00',
        ]);
        $chain = Chain::fromArray([
            'public_key' => '00',
            'period' => 30,
            'genesis_time' => 1000,
            'hash' => 'h',
            'groupHash' => 'g',
            'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
            'beaconID' => 'mainnet',
            'metadata' => ['beaconID' => 'mainnet'],
        ]);
        self::assertTrue($verifier->verify($beacon, $chain));
    }

    /**
     * Test verify with a mock backend that returns false.
     *
     * @return void
     */
    public function testVerifyWithMockBackendFalse(): void
    {
        $backend = new class implements VerifierBackendInterface {
            public function supportsScheme(\Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return true;
            }
            public function verify(string $signature, string $message, string $publicKey, \Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return false;
            }
        };
        $verifier = new Verifier(['mock' => $backend]);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => '00',
            'signature' => '00',
        ]);
        $chain = Chain::fromArray([
            'public_key' => '00',
            'period' => 30,
            'genesis_time' => 1000,
            'hash' => 'h',
            'groupHash' => 'g',
            'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
            'beaconID' => 'mainnet',
            'metadata' => ['beaconID' => 'mainnet'],
        ]);
        self::assertFalse($verifier->verify($beacon, $chain));
    }

    /**
     * Test verify with a mock backend that throws.
     *
     * @return void
     */
    public function testVerifyWithMockBackendThrows(): void
    {
        $backend = new class implements VerifierBackendInterface {
            public function supportsScheme(\Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return false;
            }
            public function verify(string $signature, string $message, string $publicKey, \Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                throw new \RuntimeException('Should not be called');
            }
        };
        $verifier = new Verifier(['mock' => $backend]);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => '00',
            'signature' => '00',
        ]);
        $chain = Chain::fromArray([
            'public_key' => '00',
            'period' => 30,
            'genesis_time' => 1000,
            'hash' => 'h',
            'groupHash' => 'g',
            'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
            'beaconID' => 'mainnet',
            'metadata' => ['beaconID' => 'mainnet'],
        ]);
        $this->expectException(\Drand\Client\Exception\VerificationUnavailableException::class);
        $verifier->verify($beacon, $chain);
    }

    /**
     * Test backend selection logic.
     *
     * @return void
     */
    public function testVerifyBackendSelection(): void
    {
        $backend = new class implements VerifierBackendInterface {
            public function supportsScheme(\Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return $scheme === SignatureScheme::PEDERSEN_BLS_CHAINED;
            }
            public function verify(string $signature, string $message, string $publicKey, \Drand\Client\Enum\SignatureScheme $scheme): bool
            {
                return true;
            }
        };
        $verifier = new Verifier(['mock' => $backend]);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => '00',
            'signature' => '00',
        ]);
        $chain = Chain::fromArray([
            'public_key' => '00',
            'period' => 30,
            'genesis_time' => 1000,
            'hash' => 'h',
            'groupHash' => 'g',
            'schemeID' => SignatureScheme::PEDERSEN_BLS_CHAINED->value,
            'beaconID' => 'mainnet',
            'metadata' => ['beaconID' => 'mainnet'],
        ]);
        self::assertTrue($verifier->verify($beacon, $chain));
    }
}
