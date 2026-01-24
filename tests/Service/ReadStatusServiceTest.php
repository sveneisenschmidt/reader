<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Domain\ItemStatus\Service\ReadStatusService;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ReadStatusServiceTest extends KernelTestCase
{
    private ReadStatusService $service;
    private int $testUserId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->service = $container->get(ReadStatusService::class);
        $this->testUserId = $this->getOrCreateTestUser();
    }

    private function getOrCreateTestUser(): int
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = $userRepository->findByEmail('readstatus_test@example.com');
        if (!$user) {
            $user = new User('readstatus-test-user');
            $user->setEmail('readstatus_test@example.com');
            $user->setPassword(
                $passwordHasher->hashPassword($user, 'testpassword'),
            );
            $user->setTotpSecret('JBSWY3DPEHPK3PXP');
            $userRepository->save($user);
        }

        return $user->getId();
    }

    #[Test]
    public function markAsReadAndIsRead(): void
    {
        $guid = 'testitm_'.uniqid();

        $this->assertFalse($this->service->isRead($this->testUserId, $guid));

        $this->service->markAsRead($this->testUserId, $guid);

        $this->assertTrue($this->service->isRead($this->testUserId, $guid));
    }

    #[Test]
    public function markAsUnread(): void
    {
        $guid = 'unread_'.uniqid();

        $this->service->markAsRead($this->testUserId, $guid);
        $this->assertTrue($this->service->isRead($this->testUserId, $guid));

        $this->service->markAsUnread($this->testUserId, $guid);
        $this->assertFalse($this->service->isRead($this->testUserId, $guid));
    }

    #[Test]
    public function markManyAsRead(): void
    {
        $guids = [
            'many1_'.uniqid(),
            'many2_'.uniqid(),
            'many3_'.uniqid(),
        ];

        foreach ($guids as $guid) {
            $this->assertFalse(
                $this->service->isRead($this->testUserId, $guid),
            );
        }

        $this->service->markManyAsRead($this->testUserId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->service->isRead($this->testUserId, $guid));
        }
    }
}
