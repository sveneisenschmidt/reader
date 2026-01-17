<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\ItemStatus\Repository\BookmarkStatusRepository;
use App\Domain\ItemStatus\Service\BookmarkService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookmarkServiceTest extends TestCase
{
    #[Test]
    public function bookmarkDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createMock(BookmarkStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('bookmark')
            ->with($userId, $guid);

        $service = new BookmarkService($repository);
        $service->bookmark($userId, $guid);
    }

    #[Test]
    public function unbookmarkDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createMock(BookmarkStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('unbookmark')
            ->with($userId, $guid);

        $service = new BookmarkService($repository);
        $service->unbookmark($userId, $guid);
    }

    #[Test]
    public function isBookmarkedReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createStub(BookmarkStatusRepository::class);
        $repository->method('isBookmarked')->willReturn(true);

        $service = new BookmarkService($repository);

        $this->assertTrue($service->isBookmarked($userId, $guid));
    }

    #[Test]
    public function isBookmarkedReturnsFalseWhenNotBookmarked(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createStub(BookmarkStatusRepository::class);
        $repository->method('isBookmarked')->willReturn(false);

        $service = new BookmarkService($repository);

        $this->assertFalse($service->isBookmarked($userId, $guid));
    }

    #[Test]
    public function getBookmarkedGuidsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $expectedGuids = ['guid1', 'guid2', 'guid3'];

        $repository = $this->createStub(BookmarkStatusRepository::class);
        $repository->method('getBookmarkedGuidsForUser')->willReturn($expectedGuids);

        $service = new BookmarkService($repository);

        $this->assertSame($expectedGuids, $service->getBookmarkedGuidsForUser($userId));
    }

    #[Test]
    public function countByUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $expectedCount = 5;

        $repository = $this->createStub(BookmarkStatusRepository::class);
        $repository->method('countByUser')->willReturn($expectedCount);

        $service = new BookmarkService($repository);

        $this->assertSame($expectedCount, $service->countByUser($userId));
    }
}
