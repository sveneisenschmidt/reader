<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Users;

use App\Entity\Users\UserPreference;
use App\Repository\Users\UserPreferenceRepository;
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
        $result = $this->repository->isEnabled(99999, 'non_existent_key');

        $this->assertFalse($result);
    }

    #[Test]
    public function setEnabledCreatesNewPreference(): void
    {
        $userId = 99998;
        $key = UserPreference::SHOW_NEXT_UNREAD;

        $this->repository->setEnabled($userId, $key, true);

        $this->assertTrue($this->repository->isEnabled($userId, $key));
    }

    #[Test]
    public function setEnabledUpdatesExistingPreference(): void
    {
        $userId = 99997;
        $key = UserPreference::SHOW_NEXT_UNREAD;

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
            UserPreference::SHOW_NEXT_UNREAD,
            true,
        );

        $result = $this->repository->getAllForUser($userId);

        $this->assertCount(1, $result);
        $this->assertTrue($result[UserPreference::SHOW_NEXT_UNREAD]);
    }

    #[Test]
    public function getValueReturnsDefaultWhenPreferenceDoesNotExist(): void
    {
        $result = $this->repository->getValue(
            99994,
            'non_existent_key',
            'default',
        );

        $this->assertEquals('default', $result);
    }

    #[Test]
    public function setValueCreatesNewPreference(): void
    {
        $userId = 99993;
        $key = UserPreference::FILTER_WORDS;

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
        $key = UserPreference::FILTER_WORDS;

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
