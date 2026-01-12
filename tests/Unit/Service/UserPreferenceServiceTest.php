<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Enum\PreferenceDefault;
use App\Enum\PreferenceKey;
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
            ->with(
                $userId,
                PreferenceKey::ShowNextUnread,
                PreferenceDefault::ShowNextUnread->asBool(),
            )
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
            ->with(
                $userId,
                PreferenceKey::ShowNextUnread,
                PreferenceDefault::ShowNextUnread->asBool(),
            )
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
            ->with($userId, PreferenceKey::ShowNextUnread, true);

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
            ->with(
                $userId,
                PreferenceKey::PullToRefresh,
                PreferenceDefault::PullToRefresh->asBool(),
            )
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
            ->with(
                $userId,
                PreferenceKey::PullToRefresh,
                PreferenceDefault::PullToRefresh->asBool(),
            )
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
            ->with($userId, PreferenceKey::PullToRefresh, false);

        $service = new UserPreferenceService($repository);
        $service->setPullToRefresh($userId, false);
    }

    #[Test]
    public function isUnreadOnlyEnabledReturnsTrueByDefault(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('isEnabled')
            ->with(
                $userId,
                PreferenceKey::UnreadOnly,
                PreferenceDefault::UnreadOnly->asBool(),
            )
            ->willReturn(true);

        $service = new UserPreferenceService($repository);

        $this->assertTrue($service->isUnreadOnlyEnabled($userId));
    }

    #[Test]
    public function isUnreadOnlyEnabledReturnsFalseWhenDisabled(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('isEnabled')
            ->with(
                $userId,
                PreferenceKey::UnreadOnly,
                PreferenceDefault::UnreadOnly->asBool(),
            )
            ->willReturn(false);

        $service = new UserPreferenceService($repository);

        $this->assertFalse($service->isUnreadOnlyEnabled($userId));
    }

    #[Test]
    public function setUnreadOnlyCallsRepository(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('setEnabled')
            ->with($userId, PreferenceKey::UnreadOnly, false);

        $service = new UserPreferenceService($repository);
        $service->setUnreadOnly($userId, false);
    }

    #[Test]
    public function getAllPreferencesReturnsAllPreferences(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->method('isEnabled')
            ->willReturnMap([
                [
                    $userId,
                    PreferenceKey::ShowNextUnread,
                    PreferenceDefault::ShowNextUnread->asBool(),
                    true,
                ],
                [
                    $userId,
                    PreferenceKey::PullToRefresh,
                    PreferenceDefault::PullToRefresh->asBool(),
                    false,
                ],
                [
                    $userId,
                    PreferenceKey::UnreadOnly,
                    PreferenceDefault::UnreadOnly->asBool(),
                    true,
                ],
            ]);
        $repository
            ->method('getValue')
            ->with(
                $userId,
                PreferenceKey::FilterWords,
                PreferenceDefault::FilterWords->value(),
            )
            ->willReturn("word1\nword2");

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertTrue($result[PreferenceKey::ShowNextUnread->value]);
        $this->assertFalse($result[PreferenceKey::PullToRefresh->value]);
        $this->assertTrue($result[PreferenceKey::UnreadOnly->value]);
        $this->assertEquals(
            "word1\nword2",
            $result[PreferenceKey::FilterWords->value],
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
                [
                    $userId,
                    PreferenceKey::ShowNextUnread,
                    PreferenceDefault::ShowNextUnread->asBool(),
                    false,
                ],
                [
                    $userId,
                    PreferenceKey::PullToRefresh,
                    PreferenceDefault::PullToRefresh->asBool(),
                    true,
                ],
                [
                    $userId,
                    PreferenceKey::UnreadOnly,
                    PreferenceDefault::UnreadOnly->asBool(),
                    true,
                ],
            ]);
        $repository
            ->method('getValue')
            ->with(
                $userId,
                PreferenceKey::FilterWords,
                PreferenceDefault::FilterWords->value(),
            )
            ->willReturn('');

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertFalse($result[PreferenceKey::ShowNextUnread->value]);
        $this->assertTrue($result[PreferenceKey::PullToRefresh->value]);
        $this->assertTrue($result[PreferenceKey::UnreadOnly->value]);
        $this->assertEquals('', $result[PreferenceKey::FilterWords->value]);
    }
}
