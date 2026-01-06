<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Users\User;
use App\Repository\Users\UserRepository;
use App\Service\UserService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private UserService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->service = new UserService($this->userRepository);
    }

    #[Test]
    public function getCurrentUserReturnsRepositoryResult(): void
    {
        $user = $this->createMock(User::class);

        $this->userRepository
            ->expects($this->once())
            ->method('getOrCreateDummyUser')
            ->willReturn($user);

        $result = $this->service->getCurrentUser();

        $this->assertSame($user, $result);
    }

    #[Test]
    public function isDummyUserReturnsTrueForDummyEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('dummy@reader.local');

        $this->assertTrue($this->service->isDummyUser($user));
    }

    #[Test]
    public function isDummyUserReturnsFalseForOtherEmail(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');

        $this->assertFalse($this->service->isDummyUser($user));
    }
}
