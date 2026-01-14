<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ReadStatusService;
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

        $user = $userRepository->findByUsername('readstatus_test@example.com');
        if (!$user) {
            $user = new User('readstatus_test@example.com');
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
            $this->assertFalse($this->service->isRead($this->testUserId, $guid));
        }

        $this->service->markManyAsRead($this->testUserId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->service->isRead($this->testUserId, $guid));
        }
    }

    #[Test]
    public function getReadGuidsForUser(): void
    {
        $guid1 = 'getguids1_'.uniqid();
        $guid2 = 'getguids2_'.uniqid();

        $this->service->markAsRead($this->testUserId, $guid1);
        $this->service->markAsRead($this->testUserId, $guid2);

        $readGuids = $this->service->getReadGuidsForUser($this->testUserId);

        $this->assertContains($guid1, $readGuids);
        $this->assertContains($guid2, $readGuids);
    }

    #[Test]
    public function enrichItemsWithReadStatus(): void
    {
        $readGuid = 'enrich_read_'.uniqid();
        $unreadGuid = 'enrich_unread_'.uniqid();

        $this->service->markAsRead($this->testUserId, $readGuid);

        $items = [
            ['guid' => $readGuid, 'title' => 'Read Item'],
            ['guid' => $unreadGuid, 'title' => 'Unread Item'],
        ];

        $enrichedItems = $this->service->enrichItemsWithReadStatus(
            $items,
            $this->testUserId,
        );

        $this->assertTrue($enrichedItems[0]['isRead']);
        $this->assertFalse($enrichedItems[1]['isRead']);
    }
}
