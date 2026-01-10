<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Repository\Users\UserRepository;
use App\Service\UserRegistrationService;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRegistrationServiceTest extends KernelTestCase
{
    private UserRegistrationService $service;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->service = $container->get(UserRegistrationService::class);
        $this->userRepository = $container->get(UserRepository::class);
    }

    #[Test]
    public function registerCreatesUserWithHashedPassword(): void
    {
        $email = 'newuser_'.uniqid().'@example.com';
        $password = 'testpassword123';
        $totpSecret = 'JBSWY3DPEHPK3PXP';

        $user = $this->service->register($email, $password, $totpSecret);

        $this->assertNotNull($user->getId());
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($email, $user->getUsername());
        $this->assertNotEquals($password, $user->getPassword());
        $this->assertEquals($totpSecret, $user->getTotpSecret());
    }

    #[Test]
    public function registerPersistsUserToDatabase(): void
    {
        $email = 'persisted_'.uniqid().'@example.com';
        $password = 'testpassword123';
        $totpSecret = 'JBSWY3DPEHPK3PXP';

        $user = $this->service->register($email, $password, $totpSecret);

        $foundUser = $this->userRepository->findByEmail($email);
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->getId(), $foundUser->getId());
    }
}
