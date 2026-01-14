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
            $this->assertFalse(
                $this->service->isSeen($this->testUserId, $guid),
            );
        }

        $this->service->markManyAsSeen($this->testUserId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->service->isSeen($this->testUserId, $guid));
        }
    }
}
