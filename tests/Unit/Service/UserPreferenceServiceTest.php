<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\User\Repository\UserPreferenceRepository;
use App\Domain\User\Service\UserPreferenceService;
use App\Enum\PreferenceDefault;
use App\Enum\PreferenceKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserPreferenceServiceTest extends TestCase
{
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
    public function getFilterWordsReturnsArrayOfWords(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('getValue')
            ->with(
                $userId,
                PreferenceKey::FilterWords,
                PreferenceDefault::FilterWords->value(),
            )
            ->willReturn("word1\nword2\nword3");

        $service = new UserPreferenceService($repository);

        $this->assertEquals(
            ['word1', 'word2', 'word3'],
            $service->getFilterWords($userId),
        );
    }

    #[Test]
    public function getFilterWordsReturnsEmptyArrayWhenEmpty(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('getValue')
            ->with(
                $userId,
                PreferenceKey::FilterWords,
                PreferenceDefault::FilterWords->value(),
            )
            ->willReturn('');

        $service = new UserPreferenceService($repository);

        $this->assertEquals([], $service->getFilterWords($userId));
    }

    #[Test]
    public function getFilterWordsRawReturnsRawString(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('getValue')
            ->with(
                $userId,
                PreferenceKey::FilterWords,
                PreferenceDefault::FilterWords->value(),
            )
            ->willReturn("word1\nword2");

        $service = new UserPreferenceService($repository);

        $this->assertEquals(
            "word1\nword2",
            $service->getFilterWordsRaw($userId),
        );
    }

    #[Test]
    public function setFilterWordsCallsRepository(): void
    {
        $userId = 1;

        $repository = $this->createMock(UserPreferenceRepository::class);
        $repository
            ->expects($this->once())
            ->method('setValue')
            ->with($userId, PreferenceKey::FilterWords, "word1\nword2");

        $service = new UserPreferenceService($repository);
        $service->setFilterWords($userId, "word1\nword2");
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
                    PreferenceKey::PullToRefresh,
                    PreferenceDefault::PullToRefresh->asBool(),
                    false,
                ],
                [
                    $userId,
                    PreferenceKey::AutoMarkRead,
                    PreferenceDefault::AutoMarkRead->asBool(),
                    true,
                ],
                [
                    $userId,
                    PreferenceKey::KeyboardShortcuts,
                    PreferenceDefault::KeyboardShortcuts->asBool(),
                    true,
                ],
                [
                    $userId,
                    PreferenceKey::Bookmarks,
                    PreferenceDefault::Bookmarks->asBool(),
                    true,
                ],
            ]);
        $repository
            ->method('getValue')
            ->willReturnMap([
                [
                    $userId,
                    PreferenceKey::Theme,
                    PreferenceDefault::Theme->value(),
                    'dark',
                ],
                [
                    $userId,
                    PreferenceKey::FilterWords,
                    PreferenceDefault::FilterWords->value(),
                    "word1\nword2",
                ],
            ]);

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertEquals('dark', $result[PreferenceKey::Theme->value]);
        $this->assertFalse($result[PreferenceKey::PullToRefresh->value]);
        $this->assertTrue($result[PreferenceKey::AutoMarkRead->value]);
        $this->assertTrue($result[PreferenceKey::KeyboardShortcuts->value]);
        $this->assertTrue($result[PreferenceKey::Bookmarks->value]);
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
                    PreferenceKey::PullToRefresh,
                    PreferenceDefault::PullToRefresh->asBool(),
                    true,
                ],
                [
                    $userId,
                    PreferenceKey::AutoMarkRead,
                    PreferenceDefault::AutoMarkRead->asBool(),
                    false,
                ],
                [
                    $userId,
                    PreferenceKey::KeyboardShortcuts,
                    PreferenceDefault::KeyboardShortcuts->asBool(),
                    false,
                ],
                [
                    $userId,
                    PreferenceKey::Bookmarks,
                    PreferenceDefault::Bookmarks->asBool(),
                    true,
                ],
            ]);
        $repository
            ->method('getValue')
            ->willReturnMap([
                [
                    $userId,
                    PreferenceKey::Theme,
                    PreferenceDefault::Theme->value(),
                    'auto',
                ],
                [
                    $userId,
                    PreferenceKey::FilterWords,
                    PreferenceDefault::FilterWords->value(),
                    '',
                ],
            ]);

        $service = new UserPreferenceService($repository);
        $result = $service->getAllPreferences($userId);

        $this->assertEquals('auto', $result[PreferenceKey::Theme->value]);
        $this->assertTrue($result[PreferenceKey::PullToRefresh->value]);
        $this->assertFalse($result[PreferenceKey::AutoMarkRead->value]);
        $this->assertFalse($result[PreferenceKey::KeyboardShortcuts->value]);
        $this->assertTrue($result[PreferenceKey::Bookmarks->value]);
        $this->assertEquals('', $result[PreferenceKey::FilterWords->value]);
    }
}
