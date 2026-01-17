<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Domain\User\Service\TotpService;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        $this->totpService = new TotpService();
    }

    #[Test]
    public function generateSecretReturnsNonEmptyString(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertNotEmpty($secret);
        $this->assertIsString($secret);
    }

    #[Test]
    public function generateSecretReturnsBase32EncodedString(): void
    {
        $secret = $this->totpService->generateSecret();

        // Base32 characters
        $this->assertMatchesRegularExpression(
            '/^[A-Z2-7]+$/',
            $secret,
        );
    }

    #[Test]
    public function generateSecretReturnsUniqueValues(): void
    {
        $secret1 = $this->totpService->generateSecret();
        $secret2 = $this->totpService->generateSecret();

        $this->assertNotEquals($secret1, $secret2);
    }

    #[Test]
    public function verifyReturnsTrueForValidCode(): void
    {
        $secret = $this->totpService->generateSecret();

        // Generate a valid code using the same library
        $totp = TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $this->assertTrue($this->totpService->verify($secret, $validCode));
    }

    #[Test]
    public function verifyReturnsFalseForInvalidCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertFalse($this->totpService->verify($secret, '000000'));
        $this->assertFalse($this->totpService->verify($secret, '123456'));
        $this->assertFalse($this->totpService->verify($secret, '999999'));
    }

    #[Test]
    public function verifyReturnsFalseForEmptyCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertFalse($this->totpService->verify($secret, ''));
    }

    #[Test]
    public function getProvisioningUriReturnsOtpauthUri(): void
    {
        $secret = $this->totpService->generateSecret();

        $uri = $this->totpService->getProvisioningUri($secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('Reader', $uri);
        $this->assertStringContainsString('secret=', $uri);
    }

    #[Test]
    public function getProvisioningUriContainsSecret(): void
    {
        $secret = $this->totpService->generateSecret();

        $uri = $this->totpService->getProvisioningUri($secret);

        $this->assertStringContainsString("secret={$secret}", $uri);
    }

    #[Test]
    public function getQrCodeDataUriReturnsPngDataUri(): void
    {
        $secret = $this->totpService->generateSecret();

        $dataUri = $this->totpService->getQrCodeDataUri($secret);

        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    #[Test]
    public function getQrCodeDataUriReturnsValidBase64(): void
    {
        $secret = $this->totpService->generateSecret();

        $dataUri = $this->totpService->getQrCodeDataUri($secret);

        // Extract base64 part
        $base64 = str_replace('data:image/png;base64,', '', $dataUri);

        // Verify it's valid base64 by decoding it
        $decoded = base64_decode($base64, true);
        $this->assertNotFalse($decoded);
        $this->assertNotEmpty($decoded);
    }

    #[Test]
    public function getQrCodeDataUriGeneratesValidPngImage(): void
    {
        $secret = $this->totpService->generateSecret();

        $dataUri = $this->totpService->getQrCodeDataUri($secret);

        // Extract and decode base64
        $base64 = str_replace('data:image/png;base64,', '', $dataUri);
        $imageData = base64_decode($base64, true);

        // PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
        $pngMagic = "\x89PNG\r\n\x1a\n";
        $this->assertStringStartsWith($pngMagic, $imageData);
    }
}
