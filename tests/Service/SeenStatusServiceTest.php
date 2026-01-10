<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Entity\Users\User;
use App\Repository\Users\UserRepository;
use App\Service\SeenStatusService;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SeenStatusServiceTest extends KernelTestCase
{
    private SeenStatusService $service;
    private int $testUserId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->service = $container->get(SeenStatusService::class);
        $this->testUserId = $this->getOrCreateTestUser();
    }

    private function getOrCreateTestUser(): int
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = $userRepository->findByUsername('seenstatus_test@example.com');
        if (!$user) {
            $user = new User('seenstatus_test@example.com');
            $user->setEmail('seenstatus_test@example.com');
            $user->setPassword(
                $passwordHasher->hashPassword($user, 'testpassword'),
            );
            $user->setTotpSecret('JBSWY3DPEHPK3PXP');
            $userRepository->save($user);
        }

        return $user->getId();
    }

    #[Test]
    public function markAsSeenAndIsSeen(): void
    {
        $guid = 'seen_'.uniqid();

        $this->assertFalse($this->service->isSeen($this->testUserId, $guid));

        $this->service->markAsSeen($this->testUserId, $guid);

        $this->assertTrue($this->service->isSeen($this->testUserId, $guid));
    }

    #[Test]
    public function markManyAsSeen(): void
    {
        $guids = [
            'manyseen1_'.uniqid(),
            'manyseen2_'.uniqid(),
            'manyseen3_'.uniqid(),
        ];

        foreach ($guids as $guid) {
            $this->assertFalse($this->service->isSeen($this->testUserId, $guid));
        }

        $this->service->markManyAsSeen($this->testUserId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->service->isSeen($this->testUserId, $guid));
        }
    }

    #[Test]
    public function getSeenGuidsForUser(): void
    {
        $guid1 = 'getseenguids1_'.uniqid();
        $guid2 = 'getseenguids2_'.uniqid();

        $this->service->markAsSeen($this->testUserId, $guid1);
        $this->service->markAsSeen($this->testUserId, $guid2);

        $seenGuids = $this->service->getSeenGuidsForUser($this->testUserId);

        $this->assertContains($guid1, $seenGuids);
        $this->assertContains($guid2, $seenGuids);
    }

    #[Test]
    public function getSeenGuidsForUserWithFilter(): void
    {
        $guid1 = 'filterseen1_'.uniqid();
        $guid2 = 'filterseen2_'.uniqid();
        $guid3 = 'filterseen3_'.uniqid();

        $this->service->markAsSeen($this->testUserId, $guid1);
        $this->service->markAsSeen($this->testUserId, $guid2);
        $this->service->markAsSeen($this->testUserId, $guid3);

        $seenGuids = $this->service->getSeenGuidsForUser(
            $this->testUserId,
            [$guid1, $guid2],
        );

        $this->assertContains($guid1, $seenGuids);
        $this->assertContains($guid2, $seenGuids);
        $this->assertNotContains($guid3, $seenGuids);
    }

    #[Test]
    public function enrichItemsWithSeenStatus(): void
    {
        $seenGuid = 'enrich_seen_'.uniqid();
        $newGuid = 'enrich_new_'.uniqid();

        $this->service->markAsSeen($this->testUserId, $seenGuid);

        $items = [
            ['guid' => $seenGuid, 'title' => 'Seen Item'],
            ['guid' => $newGuid, 'title' => 'New Item'],
        ];

        $enrichedItems = $this->service->enrichItemsWithSeenStatus(
            $items,
            $this->testUserId,
        );

        $this->assertFalse($enrichedItems[0]['isNew']);
        $this->assertTrue($enrichedItems[1]['isNew']);
    }
}
