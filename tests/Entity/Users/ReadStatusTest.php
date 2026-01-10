<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity\Users;

use App\Entity\Users\ReadStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReadStatusTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $readStatus = new ReadStatus(42, "abc123");

        $this->assertEquals(42, $readStatus->getUserId());
        $this->assertEquals("abc123", $readStatus->getFeedItemGuid());
        $this->assertInstanceOf(\DateTimeImmutable::class, $readStatus->getReadAt());
    }

    #[Test]
    public function getIdReturnsNullForNewEntity(): void
    {
        $readStatus = new ReadStatus(1, "guid");

        $this->assertNull($readStatus->getId());
    }

    #[Test]
    public function readAtIsSetToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $readStatus = new ReadStatus(1, "guid");
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $readStatus->getReadAt());
        $this->assertLessThanOrEqual($after, $readStatus->getReadAt());
    }
}
