<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Command;

use App\Service\EncryptionService;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class EncryptCommandTest extends KernelTestCase
{
    #[Test]
    public function encryptsValue(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('reader:encrypt');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['value' => 'test-password']);

        $this->assertEquals(0, $commandTester->getStatusCode());

        $encrypted = trim($commandTester->getDisplay());
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals('test-password', $encrypted);

        // Verify it can be decrypted
        $encryption = static::getContainer()->get(EncryptionService::class);
        $decrypted = $encryption->decrypt($encrypted);
        $this->assertEquals('test-password', $decrypted);
    }
}
