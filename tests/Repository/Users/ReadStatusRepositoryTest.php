<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Users;

use App\Repository\Users\ReadStatusRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReadStatusRepositoryTest extends KernelTestCase
{
    private ReadStatusRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(
            ReadStatusRepository::class,
        );
    }

    #[Test]
    public function markManyAsReadWithEmptyArrayDoesNothing(): void
    {
        $this->repository->markManyAsRead(1, []);

        $this->assertTrue(true);
    }

    #[Test]
    public function deleteByFeedItemGuidsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->deleteByFeedItemGuids(1, []);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function markAsReadIsIdempotent(): void
    {
        $userId = 1;
        $feedItemGuid = 'test-guid-idempotent';

        $this->repository->markAsRead($userId, $feedItemGuid);
        $this->repository->markAsRead($userId, $feedItemGuid);

        $this->assertTrue($this->repository->isRead($userId, $feedItemGuid));
    }

    #[Test]
    public function markAsReadCreatesRecord(): void
    {
        $userId = 1;
        $feedItemGuid = 'test-guid-new';

        $this->assertFalse($this->repository->isRead($userId, $feedItemGuid));

        $this->repository->markAsRead($userId, $feedItemGuid);

        $this->assertTrue($this->repository->isRead($userId, $feedItemGuid));
    }
}
