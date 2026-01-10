<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Service\EncryptionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private EncryptionService $service;

    protected function setUp(): void
    {
        $this->service = new EncryptionService('test-secret-key');
    }

    #[Test]
    public function encryptAndDecrypt(): void
    {
        $plaintext = 'MY_TOTP_SECRET_123';

        $encrypted = $this->service->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted));
    }

    #[Test]
    public function encryptProducesDifferentOutputEachTime(): void
    {
        $plaintext = 'MY_TOTP_SECRET_123';

        $encrypted1 = $this->service->encrypt($plaintext);
        $encrypted2 = $this->service->encrypt($plaintext);

        $this->assertNotEquals($encrypted1, $encrypted2);
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted1));
        $this->assertEquals($plaintext, $this->service->decrypt($encrypted2));
    }

    #[Test]
    public function decryptWithInvalidBase64ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data');

        $this->service->decrypt('!!!invalid-base64!!!');
    }

    #[Test]
    public function decryptWithTamperedDataThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $encrypted = $this->service->encrypt('secret');
        $tampered = base64_encode('tampered'.base64_decode($encrypted));

        $this->service->decrypt($tampered);
    }

    #[Test]
    public function decryptWithDifferentKeyFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $encrypted = $this->service->encrypt('secret');

        $otherService = new EncryptionService('different-key');
        $otherService->decrypt($encrypted);
    }

    #[Test]
    public function handlesLongSecrets(): void
    {
        $longSecret = str_repeat('ABCDEFGH', 20);

        $encrypted = $this->service->encrypt($longSecret);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($longSecret, $decrypted);
    }

    #[Test]
    public function handlesBase32TotpSecret(): void
    {
        $totpSecret = 'JBSWY3DPEHPK3PXP';

        $encrypted = $this->service->encrypt($totpSecret);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($totpSecret, $decrypted);
    }
}
