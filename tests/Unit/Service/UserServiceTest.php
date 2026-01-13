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
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserServiceTest extends TestCase
{
    #[Test]
    public function getCurrentUserReturnsAuthenticatedUser(): void
    {
        $user = $this->createStub(User::class);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $service = new UserService($security);
        $result = $service->getCurrentUser();

        $this->assertSame($user, $result);
    }

    #[Test]
    public function getCurrentUserThrowsWhenNotAuthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $service = new UserService($security);

        $this->expectException(AccessDeniedException::class);
        $service->getCurrentUser();
    }

    #[Test]
    public function getCurrentUserOrNullReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createStub(User::class);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $service = new UserService($security);
        $result = $service->getCurrentUserOrNull();

        $this->assertSame($user, $result);
    }

    #[Test]
    public function getCurrentUserOrNullReturnsNullWhenNotAuthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $service = new UserService($security);
        $result = $service->getCurrentUserOrNull();

        $this->assertNull($result);
    }
}
