<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Twig;

use App\Entity\Users\User;
use App\Service\SubscriptionService;
use App\Service\UserService;
use App\Twig\RefreshTimestampExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RefreshTimestampExtensionTest extends TestCase
{
    private SubscriptionService&MockObject $subscriptionService;
    private UserService&MockObject $userService;
    private RefreshTimestampExtension $extension;

    protected function setUp(): void
    {
        $this->subscriptionService = $this->createMock(
            SubscriptionService::class,
        );
        $this->userService = $this->createMock(UserService::class);
        $this->extension = new RefreshTimestampExtension(
            $this->subscriptionService,
            $this->userService,
        );
    }

    #[Test]
    public function getFunctionsReturnsExpectedFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertEquals('last_refresh', $functions[0]->getName());
    }

    #[Test]
    public function getLastRefreshReturnsTimestampFromSubscriptionService(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $expectedTime = new \DateTimeImmutable('2024-01-15 12:00:00');

        $this->userService->method('getCurrentUser')->willReturn($user);
        $this->subscriptionService
            ->method('getOldestRefreshTime')
            ->with(1)
            ->willReturn($expectedTime);

        $result = $this->extension->getLastRefresh();

        $this->assertEquals($expectedTime, $result);
    }

    #[Test]
    public function getLastRefreshReturnsNullWhenNoSubscriptions(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->userService->method('getCurrentUser')->willReturn($user);
        $this->subscriptionService
            ->method('getOldestRefreshTime')
            ->with(1)
            ->willReturn(null);

        $result = $this->extension->getLastRefresh();

        $this->assertNull($result);
    }
}
