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

    #[Test]
    public function markManyAsReadCreatesRecords(): void
    {
        $userId = 1;
        $guids = ['batch-read-1', 'batch-read-2', 'batch-read-3'];

        foreach ($guids as $guid) {
            $this->assertFalse($this->repository->isRead($userId, $guid));
        }

        $this->repository->markManyAsRead($userId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->repository->isRead($userId, $guid));
        }
    }

    #[Test]
    public function markManyAsReadIsIdempotent(): void
    {
        $userId = 1;
        $guids = ['batch-read-idem-1', 'batch-read-idem-2'];

        $this->repository->markManyAsRead($userId, $guids);
        $this->repository->markManyAsRead($userId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->repository->isRead($userId, $guid));
        }
    }
}
