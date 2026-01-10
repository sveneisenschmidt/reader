<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Users;

use App\Repository\Users\UserRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(UserRepository::class);
    }

    #[Test]
    public function findByUsernameReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByUsername('nonexistent_user_12345');

        $this->assertNull($result);
    }

    #[Test]
    public function findByEmailReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($result);
    }

    #[Test]
    public function hasAnyUserReturnsBoolean(): void
    {
        $result = $this->repository->hasAnyUser();

        $this->assertIsBool($result);
    }

    #[Test]
    public function upgradePasswordUpdatesUserPassword(): void
    {
        $user = $this->repository->findByUsername('test@example.com');

        if ($user === null) {
            $this->markTestSkipped('Test user not found');
        }

        $originalPassword = $user->getPassword();
        $newPassword = 'new_hashed_password_'.time();

        $this->repository->upgradePassword($user, $newPassword);

        $updatedUser = $this->repository->findByUsername('test@example.com');
        $this->assertEquals($newPassword, $updatedUser->getPassword());

        // Restore original password
        $this->repository->upgradePassword($user, $originalPassword);
    }

    #[Test]
    public function upgradePasswordDoesNothingForNonUserInterface(): void
    {
        $mockUser = $this->createMock(
            \Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface::class,
        );

        // Should not throw - just returns early
        $this->repository->upgradePassword($mockUser, 'new_password');

        $this->assertTrue(true);
    }
}
