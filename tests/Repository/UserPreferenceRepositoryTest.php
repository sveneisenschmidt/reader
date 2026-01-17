<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository;

use App\Domain\User\Repository\UserPreferenceRepository;
use App\Enum\PreferenceKey;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserPreferenceRepositoryTest extends KernelTestCase
{
    private UserPreferenceRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(
            UserPreferenceRepository::class,
        );
    }

    #[Test]
    public function isEnabledReturnsFalseWhenPreferenceDoesNotExist(): void
    {
        $result = $this->repository->isEnabled(
            99999,
            PreferenceKey::PullToRefresh,
        );

        $this->assertFalse($result);
    }

    #[Test]
    public function setEnabledCreatesNewPreference(): void
    {
        $userId = 99998;
        $key = PreferenceKey::PullToRefresh;

        $this->repository->setEnabled($userId, $key, true);

        $this->assertTrue($this->repository->isEnabled($userId, $key));
    }

    #[Test]
    public function setEnabledUpdatesExistingPreference(): void
    {
        $userId = 99997;
        $key = PreferenceKey::PullToRefresh;

        $this->repository->setEnabled($userId, $key, true);
        $this->assertTrue($this->repository->isEnabled($userId, $key));

        $this->repository->setEnabled($userId, $key, false);
        $this->assertFalse($this->repository->isEnabled($userId, $key));
    }

    #[Test]
    public function getAllForUserReturnsEmptyArrayWhenNoPreferences(): void
    {
        $result = $this->repository->getAllForUser(99996);

        $this->assertEmpty($result);
    }

    #[Test]
    public function getAllForUserReturnsAllPreferences(): void
    {
        $userId = 99995;

        $this->repository->setEnabled(
            $userId,
            PreferenceKey::PullToRefresh,
            true,
        );

        $result = $this->repository->getAllForUser($userId);

        $this->assertCount(1, $result);
        $this->assertTrue($result[PreferenceKey::PullToRefresh->value]);
    }

    #[Test]
    public function getValueReturnsDefaultWhenPreferenceDoesNotExist(): void
    {
        $result = $this->repository->getValue(
            99994,
            PreferenceKey::FilterWords,
            'default',
        );

        $this->assertEquals('default', $result);
    }

    #[Test]
    public function setValueCreatesNewPreference(): void
    {
        $userId = 99993;
        $key = PreferenceKey::FilterWords;

        $this->repository->setValue($userId, $key, "word1\nword2");

        $this->assertEquals(
            "word1\nword2",
            $this->repository->getValue($userId, $key),
        );
    }

    #[Test]
    public function setValueUpdatesExistingPreference(): void
    {
        $userId = 99992;
        $key = PreferenceKey::FilterWords;

        $this->repository->setValue($userId, $key, 'word1');
        $this->assertEquals(
            'word1',
            $this->repository->getValue($userId, $key),
        );

        $this->repository->setValue($userId, $key, 'word2');
        $this->assertEquals(
            'word2',
            $this->repository->getValue($userId, $key),
        );
    }
}
