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
use App\Service\UserService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserServiceTest extends TestCase
{
    private Security&MockObject $security;
    private UserService $service;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->service = new UserService($this->security);
    }

    #[Test]
    public function getCurrentUserReturnsAuthenticatedUser(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->expects($this->once())
            ->method("getUser")
            ->willReturn($user);

        $result = $this->service->getCurrentUser();

        $this->assertSame($user, $result);
    }

    #[Test]
    public function getCurrentUserThrowsWhenNotAuthenticated(): void
    {
        $this->security->method("getUser")->willReturn(null);

        $this->expectException(AccessDeniedException::class);

        $this->service->getCurrentUser();
    }
}
