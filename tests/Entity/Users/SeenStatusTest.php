<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity\Users;

use App\Entity\Users\SeenStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SeenStatusTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $seenStatus = new SeenStatus(42, "abc123");

        $this->assertEquals(42, $seenStatus->getUserId());
        $this->assertEquals("abc123", $seenStatus->getFeedItemGuid());
        $this->assertInstanceOf(\DateTimeImmutable::class, $seenStatus->getSeenAt());
    }

    #[Test]
    public function getIdReturnsNullForNewEntity(): void
    {
        $seenStatus = new SeenStatus(1, "guid");

        $this->assertNull($seenStatus->getId());
    }

    #[Test]
    public function seenAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $seenStatus = new SeenStatus(1, "guid");
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $seenStatus->getSeenAt());
        $this->assertLessThanOrEqual($after, $seenStatus->getSeenAt());
    }
}
