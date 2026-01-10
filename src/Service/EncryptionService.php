<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EncryptionService
{
    private string $key;

    public function __construct(
        #[Autowire('%env(APP_SECRET)%')] string $appSecret,
    ) {
        $this->key = hash('sha256', $appSecret, binary: true);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, strict: true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $this->key,
        );
        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $plaintext;
    }
}
