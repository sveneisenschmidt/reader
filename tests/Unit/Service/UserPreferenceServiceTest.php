<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Users\UserPreference;
use App\Repository\Users\UserPreferenceRepository;
use App\Service\UserPreferenceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserPreferenceServiceTest extends TestCase
{
    #[Test]
    public function isShowNextUnreadEnabledReturnsTrueWhenEnabled(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('isEnabled')
            ->with($userId, UserPreference::SHOW_NEXT_UNREAD)
            ->willReturn(true);

        $service = new UserPreferenceService($repository);

        $this->assertTrue($service->isShowNextUnreadEnabled($userId));
    }

    #[Test]
    public function isShowNextUnreadEnabledReturnsFalseWhenDisabled(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('isEnabled')
            ->with($userId, UserPreference::SHOW_NEXT_UNREAD)
            ->willReturn(false);

        $service = new UserPreferenceService($repository);

        $this->assertFalse($service->isShowNextUnreadEnabled($userId));
    }

    #[Test]
    public function setShowNextUnreadCallsRepository(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('setEnabled')
            ->with($userId, UserPreference::SHOW_NEXT_UNREAD, true);

        $service = new UserPreferenceService($repository);
        $service->setShowNextUnread($userId, true);
    }

    #[Test]
    public function getAllPreferencesReturnsAllPreferences(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('getAllForUser')
            ->with($userId)
            ->willReturn([
                UserPreference::SHOW_NEXT_UNREAD => true,
            ]);

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertTrue($result[UserPreference::SHOW_NEXT_UNREAD]);
    }

    #[Test]
    public function getAllPreferencesReturnsDefaultsWhenEmpty(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('getAllForUser')
            ->with($userId)
            ->willReturn([]);

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertFalse($result[UserPreference::SHOW_NEXT_UNREAD]);
    }
}
