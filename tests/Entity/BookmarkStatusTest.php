<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity;

use App\Entity\BookmarkStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookmarkStatusTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $bookmarkStatus = new BookmarkStatus(42, 'abc123');

        $this->assertEquals(42, $bookmarkStatus->getUserId());
        $this->assertEquals('abc123', $bookmarkStatus->getFeedItemGuid());
        $this->assertInstanceOf(\DateTimeImmutable::class, $bookmarkStatus->getBookmarkedAt());
    }

    #[Test]
    public function getIdReturnsNullForNewEntity(): void
    {
        $bookmarkStatus = new BookmarkStatus(1, 'guid');

        $this->assertNull($bookmarkStatus->getId());
    }

    #[Test]
    public function bookmarkedAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $bookmarkStatus = new BookmarkStatus(1, 'guid');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $bookmarkStatus->getBookmarkedAt());
        $this->assertLessThanOrEqual($after, $bookmarkStatus->getBookmarkedAt());
    }
}
