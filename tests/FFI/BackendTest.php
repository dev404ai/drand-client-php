<?php

declare(strict_types=1);

namespace Tests\FFI;

use PHPUnit\Framework\TestCase;
use Drand\Client\Backend\FFIBlstBackend;
use Drand\Client\Backend\PhpGmpBackend;
use Drand\Client\Backend\VerifierBackendInterface;
use Drand\Client\ValueObject\Beacon;
use Drand\Client\ValueObject\Chain;
use Drand\Client\Enum\SignatureScheme;
use Drand\Client\Exception\VerificationUnavailableException;
use Drand\Client\Verifier;

/**
 * @group unit
 * @covers \Drand\Client\Backend\PhpGmpBackend
 * @covers \Drand\Client\Backend\FFIBlstBackend
 * @covers \Drand\Client\Verifier
 */
final class BackendTest extends TestCase
{
    /**
     * Test that PhpGmpBackend throws on unsupported scheme.
     *
     * @return void
     */
    public function testPhpBackendThrowsOnUnsupportedScheme(): void
    {
        $backend = new PhpGmpBackend();
        self::assertFalse($backend->supportsScheme(SignatureScheme::BLS_UNCHAINED_G1));
        $this->expectException(\InvalidArgumentException::class);
        $backend->verify(str_repeat('\0', 96), '', str_repeat('\0', 48), SignatureScheme::BLS_UNCHAINED_G1);
    }

    /**
     * Test that PhpGmpBackend throws if GMP is not available.
     *
     * @return void
     */
    public function testPhpBackendThrowsIfNoGmp(): void
    {
        $backend = new PhpGmpBackend();
        $this->expectException(\Drand\Client\Exception\VerificationUnavailableException::class);
        $backend->verify(str_repeat("\0", 48), str_repeat("\0", 8), str_repeat("\0", 96), SignatureScheme::PEDERSEN_BLS_CHAINED);
    }

    /**
     * Test that FFIBlstBackend loads or skips if FFI is not enabled.
     *
     * @return void
     */
    public function testFFIBackendLoadsOrSkips(): void
    {
        if (!extension_loaded('ffi')) {
            self::markTestSkipped('FFI not enabled in this environment');
        }
        self::assertTrue(class_exists(FFIBlstBackend::class));
        $backend = new FFIBlstBackend();
        foreach (
            [
            SignatureScheme::PEDERSEN_BLS_CHAINED,
            SignatureScheme::PEDERSEN_BLS_UNCHAINED,
            SignatureScheme::BLS_UNCHAINED_G1,
            SignatureScheme::BLS_RFC9380_G1
            ] as $scheme
        ) {
            self::assertTrue($backend->supportsScheme($scheme));
        }
        self::assertFalse($backend->supportsScheme(SignatureScheme::BN254_ON_G1));
    }

    /**
     * Test randomness verification logic.
     *
     * @return void
     */
    public function testRandomnessVerification(): void
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

        // Tamper randomness
        $beaconInvalid = Beacon::fromArray([
            'round' => 1,
            'randomness' => bin2hex(strrev($randomness)),
            'signature' => bin2hex($sig),
        ]);
        self::assertFalse($verifier->verifyRandomness($beaconInvalid));
    }

    /**
     * Test FFIBlstBackend throws on invalid input lengths.
     *
     * @return void
     */
    public function testFFIBackendVerifyThrowsOnInvalidLengths(): void
    {
        if (!extension_loaded('ffi')) {
            self::markTestSkipped('FFI not enabled in this environment');
        }
        $backend = new FFIBlstBackend();
        $this->expectException(\InvalidArgumentException::class);
        $backend->verify(
            str_repeat("\0", 47),
            '',
            str_repeat("\0", 96),
            SignatureScheme::PEDERSEN_BLS_CHAINED
        );
        $this->expectException(\InvalidArgumentException::class);
        $backend->verify(
            str_repeat("\0", 95),
            '',
            str_repeat("\0", 48),
            SignatureScheme::BLS_UNCHAINED_G1
        );
    }

    /**
     * Test FFIBlstBackend throws on unsupported scheme.
     *
     * @return void
     */
    public function testFFIBackendVerifyThrowsOnUnsupportedScheme(): void
    {
        if (!extension_loaded('ffi')) {
            self::markTestSkipped('FFI not enabled in this environment');
        }
        $backend = new FFIBlstBackend();
        self::assertFalse($backend->supportsScheme(SignatureScheme::BN254_ON_G1));
    }

    /**
     * Test FFIBlstBackend constructor throws on missing library.
     *
     * @return void
     */
    public function testFFIBackendConstructorThrowsOnMissingLib(): void
    {
        if (!extension_loaded('ffi')) {
            self::markTestSkipped('FFI not enabled in this environment');
        }
        $this->expectException(\FFI\Exception::class);
        new FFIBlstBackend('/nonexistent/libblst.so');
    }

    /**
     * Test FFIBlstBackend supports all scheme variants.
     *
     * @return void
     */
    public function testFFIBackendSupportsSchemeVariants(): void
    {
        if (!extension_loaded('ffi')) {
            self::markTestSkipped('FFI not enabled in this environment');
        }
        $backend = new FFIBlstBackend();
        self::assertTrue($backend->supportsScheme(SignatureScheme::PEDERSEN_BLS_CHAINED));
        self::assertTrue($backend->supportsScheme(SignatureScheme::PEDERSEN_BLS_UNCHAINED));
        self::assertTrue($backend->supportsScheme(SignatureScheme::BLS_UNCHAINED_G1));
        self::assertTrue($backend->supportsScheme(SignatureScheme::BLS_RFC9380_G1));
        self::assertFalse($backend->supportsScheme(SignatureScheme::BN254_ON_G1));
    }

    /**
     * Skipped: Fuzzing FFI backend with random data.
     *
     * @return void
     */
    public function testFFIBackendVerifyWithRandomData(): void
    {
        self::markTestSkipped('FFI random data can cause memory errors; skipping for safety.');
    }

    /**
     * Test PhpGmpBackend supports all scheme variants.
     *
     * @return void
     */
    public function testPhpGmpBackendSupportsSchemeVariants(): void
    {
        $backend = new PhpGmpBackend();
        self::assertTrue($backend->supportsScheme(SignatureScheme::PEDERSEN_BLS_CHAINED));
        self::assertTrue($backend->supportsScheme(SignatureScheme::PEDERSEN_BLS_UNCHAINED));
        self::assertFalse($backend->supportsScheme(SignatureScheme::BLS_UNCHAINED_G1));
        self::assertFalse($backend->supportsScheme(SignatureScheme::BN254_ON_G1));
    }

    /**
     * Test PhpGmpBackend verify with random data.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testPhpGmpBackendVerifyWithRandomData(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('GMP not enabled in this environment');
        }
        $backend = new PhpGmpBackend();
        try {
            $result = $backend->verify(random_bytes(48), random_bytes(8), random_bytes(96), SignatureScheme::PEDERSEN_BLS_CHAINED);
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * Test PhpGmpBackend verify with all zero data.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testPhpGmpBackendVerifyWithAllZeroData(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('GMP not enabled in this environment');
        }
        $backend = new PhpGmpBackend();
        try {
            $result = $backend->verify(str_repeat("\0", 48), str_repeat("\0", 8), str_repeat("\0", 96), SignatureScheme::PEDERSEN_BLS_CHAINED);
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * Test PhpGmpBackend verify with empty data throws.
     *
     * @return void
     */
    public function testPhpGmpBackendVerifyWithEmptyData(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('GMP not enabled in this environment');
        }
        $backend = new PhpGmpBackend();
        $this->expectException(\InvalidArgumentException::class);
        $backend->verify('', '', '', SignatureScheme::PEDERSEN_BLS_CHAINED);
    }

    /**
     * Fuzz test for PhpGmpBackend verify with random inputs.
     *
     * @return void
     * @doesNotPerformAssertions
     */
    public function testPhpGmpBackendFuzzInputs(): void
    {
        if (!extension_loaded('gmp')) {
            self::markTestSkipped('GMP not enabled in this environment');
        }
        $backend = new PhpGmpBackend();
        $schemes = [SignatureScheme::PEDERSEN_BLS_CHAINED, SignatureScheme::PEDERSEN_BLS_UNCHAINED];
        for ($i = 0; $i < 20; $i++) {
            $sig = random_bytes(rand(1, 64));
            $msg = random_bytes(rand(1, 32));
            $pub = random_bytes(rand(1, 128));
            $scheme = $schemes[array_rand($schemes)];
            try {
                $result = $backend->verify($sig, $msg, $pub, $scheme);
            } catch (\InvalidArgumentException $e) {
            }
        }
    }

    /**
     * Skipped: Fuzzing FFI backend with random data.
     *
     * @return void
     */
    public function testFFIBlstBackendFuzzInputs(): void
    {
        self::markTestSkipped('FFI fuzzing skipped for safety.');
    }

    /**
     * Test randomness verification using Verifier class.
     *
     * @return void
     */
    public function testVerifierRandomness(): void
    {
        $backend = new PhpGmpBackend();
        $verifier = new Verifier(['gmp' => $backend]);
        $sig = random_bytes(48);
        $randomness = hash('sha256', $sig, true);
        $beacon = Beacon::fromArray([
            'round' => 1,
            'randomness' => bin2hex($randomness),
            'signature' => bin2hex($sig),
        ]);
        self::assertTrue($verifier->verifyRandomness($beacon));
    }
}
