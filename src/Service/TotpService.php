<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;

class TotpService
{
    private const LABEL = "Reader";

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function verify(string $secret, string $code): bool
    {
        $totp = TOTP::createFromSecret($secret);

        return $totp->verify($code);
    }

    public function getProvisioningUri(string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel(self::LABEL);

        return $totp->getProvisioningUri();
    }

    public function getQrCodeDataUri(string $secret): string
    {
        $uri = $this->getProvisioningUri($secret);

        $builder = new Builder(
            writer: new PngWriter(),
            data: $uri,
            encoding: new Encoding("UTF-8"),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 200,
            margin: 0,
        );

        return $builder->build()->getDataUri();
    }
}
