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
    public function isPullToRefreshEnabledReturnsTrueByDefault(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('isEnabled')
            ->with($userId, UserPreference::PULL_TO_REFRESH, true)
            ->willReturn(true);

        $service = new UserPreferenceService($repository);

        $this->assertTrue($service->isPullToRefreshEnabled($userId));
    }

    #[Test]
    public function isPullToRefreshEnabledReturnsFalseWhenDisabled(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('isEnabled')
            ->with($userId, UserPreference::PULL_TO_REFRESH, true)
            ->willReturn(false);

        $service = new UserPreferenceService($repository);

        $this->assertFalse($service->isPullToRefreshEnabled($userId));
    }

    #[Test]
    public function setPullToRefreshCallsRepository(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('setEnabled')
            ->with($userId, UserPreference::PULL_TO_REFRESH, false);

        $service = new UserPreferenceService($repository);
        $service->setPullToRefresh($userId, false);
    }

    #[Test]
    public function getAllPreferencesReturnsAllPreferences(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->method('isEnabled')
            ->willReturnMap([
                [$userId, UserPreference::SHOW_NEXT_UNREAD, false, true],
                [$userId, UserPreference::PULL_TO_REFRESH, true, false],
            ]);
        $repository
            ->method('getValue')
            ->with($userId, UserPreference::FILTER_WORDS, '')
            ->willReturn("word1\nword2");

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertTrue($result[UserPreference::SHOW_NEXT_UNREAD]);
        $this->assertFalse($result[UserPreference::PULL_TO_REFRESH]);
        $this->assertEquals(
            "word1\nword2",
            $result[UserPreference::FILTER_WORDS],
        );
    }

    #[Test]
    public function getAllPreferencesReturnsDefaultsWhenEmpty(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->method('isEnabled')
            ->willReturnMap([
                [$userId, UserPreference::SHOW_NEXT_UNREAD, false, false],
                [$userId, UserPreference::PULL_TO_REFRESH, true, true],
            ]);
        $repository
            ->method('getValue')
            ->with($userId, UserPreference::FILTER_WORDS, '')
            ->willReturn('');

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertFalse($result[UserPreference::SHOW_NEXT_UNREAD]);
        $this->assertTrue($result[UserPreference::PULL_TO_REFRESH]);
        $this->assertEquals('', $result[UserPreference::FILTER_WORDS]);
    }
}
