<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository;

use App\Domain\ItemStatus\Repository\BookmarkStatusRepository;
use App\Tests\Trait\DatabaseIsolationTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookmarkStatusRepositoryTest extends KernelTestCase
{
    use DatabaseIsolationTrait;

    private BookmarkStatusRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(
            BookmarkStatusRepository::class,
        );
    }

    #[Test]
    public function bookmarkCreatesRecord(): void
    {
        $userId = 1;
        $feedItemGuid = 'bookmark-test-new';

        $this->assertFalse(
            $this->repository->isBookmarked($userId, $feedItemGuid),
        );

        $this->repository->bookmark($userId, $feedItemGuid);

        $this->assertTrue(
            $this->repository->isBookmarked($userId, $feedItemGuid),
        );
    }

    #[Test]
    public function bookmarkIsIdempotent(): void
    {
        $userId = 1;
        $feedItemGuid = 'bookmark-test-idempotent';

        $this->repository->bookmark($userId, $feedItemGuid);
        $this->repository->bookmark($userId, $feedItemGuid);

        $this->assertTrue(
            $this->repository->isBookmarked($userId, $feedItemGuid),
        );
    }

    #[Test]
    public function unbookmarkRemovesRecord(): void
    {
        $userId = 1;
        $feedItemGuid = 'bookmark-test-remove';

        $this->repository->bookmark($userId, $feedItemGuid);
        $this->assertTrue(
            $this->repository->isBookmarked($userId, $feedItemGuid),
        );

        $this->repository->unbookmark($userId, $feedItemGuid);

        $this->assertFalse(
            $this->repository->isBookmarked($userId, $feedItemGuid),
        );
    }

    #[Test]
    public function unbookmarkDoesNothingForNonExisting(): void
    {
        $userId = 1;
        $feedItemGuid = 'bookmark-test-nonexisting';

        $this->repository->unbookmark($userId, $feedItemGuid);

        $this->assertFalse(
            $this->repository->isBookmarked($userId, $feedItemGuid),
        );
    }

    #[Test]
    public function getBookmarkedGuidsForUserReturnsBookmarkedGuids(): void
    {
        $userId = 997;
        $guids = ['bookmark-guids-1', 'bookmark-guids-2'];

        foreach ($guids as $guid) {
            $this->repository->bookmark($userId, $guid);
        }

        $result = $this->repository->getBookmarkedGuidsForUser($userId);

        $this->assertContains('bookmark-guids-1', $result);
        $this->assertContains('bookmark-guids-2', $result);
    }

    #[Test]
    public function getBookmarkedGuidsForUserReturnsEmptyArrayWhenNoBookmarks(): void
    {
        $userId = 996;

        $result = $this->repository->getBookmarkedGuidsForUser($userId);

        $this->assertEmpty($result);
    }

    #[Test]
    public function countByUserReturnsCorrectCount(): void
    {
        $userId = 995;

        $this->assertEquals(0, $this->repository->countByUser($userId));

        $this->repository->bookmark($userId, 'count-test-1');
        $this->repository->bookmark($userId, 'count-test-2');

        $this->assertEquals(2, $this->repository->countByUser($userId));
    }
}
