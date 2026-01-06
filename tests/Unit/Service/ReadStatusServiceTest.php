<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Repository\Users\ReadStatusRepository;
use App\Service\ReadStatusService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReadStatusServiceTest extends TestCase
{
    private ReadStatusRepository&MockObject $readStatusRepository;
    private ReadStatusService $service;

    protected function setUp(): void
    {
        $this->readStatusRepository = $this->createMock(ReadStatusRepository::class);
        $this->service = new ReadStatusService($this->readStatusRepository);
    }

    #[Test]
    public function markAsReadDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $this->readStatusRepository
            ->expects($this->once())
            ->method('markAsRead')
            ->with($userId, $guid);

        $this->service->markAsRead($userId, $guid);
    }

    #[Test]
    public function markAsUnreadDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $this->readStatusRepository
            ->expects($this->once())
            ->method('markAsUnread')
            ->with($userId, $guid);

        $this->service->markAsUnread($userId, $guid);
    }

    #[Test]
    public function markManyAsReadDelegatesToRepository(): void
    {
        $userId = 1;
        $guids = ['guid1', 'guid2', 'guid3'];

        $this->readStatusRepository
            ->expects($this->once())
            ->method('markManyAsRead')
            ->with($userId, $guids);

        $this->service->markManyAsRead($userId, $guids);
    }

    #[Test]
    public function isReadReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $this->readStatusRepository
            ->method('isRead')
            ->with($userId, $guid)
            ->willReturn(true);

        $this->assertTrue($this->service->isRead($userId, $guid));
    }

    #[Test]
    public function getReadGuidsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guids = ['guid1', 'guid2'];

        $this->readStatusRepository
            ->method('getReadGuidsForUser')
            ->with($userId)
            ->willReturn($guids);

        $result = $this->service->getReadGuidsForUser($userId);

        $this->assertEquals($guids, $result);
    }

    #[Test]
    public function enrichItemsWithReadStatusMarksReadItems(): void
    {
        $userId = 1;
        $readGuids = ['guid1', 'guid3'];

        $this->readStatusRepository
            ->method('getReadGuidsForUser')
            ->with($userId)
            ->willReturn($readGuids);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
            ['guid' => 'guid3', 'title' => 'Item 3'],
        ];

        $result = $this->service->enrichItemsWithReadStatus($items, $userId);

        $this->assertTrue($result[0]['isRead']);
        $this->assertFalse($result[1]['isRead']);
        $this->assertTrue($result[2]['isRead']);
    }

    #[Test]
    public function enrichItemsWithReadStatusHandlesEmptyReadList(): void
    {
        $userId = 1;

        $this->readStatusRepository
            ->method('getReadGuidsForUser')
            ->willReturn([]);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
        ];

        $result = $this->service->enrichItemsWithReadStatus($items, $userId);

        $this->assertFalse($result[0]['isRead']);
        $this->assertFalse($result[1]['isRead']);
    }

    #[Test]
    public function enrichItemsWithReadStatusHandlesEmptyItemsList(): void
    {
        $userId = 1;

        $this->readStatusRepository
            ->method('getReadGuidsForUser')
            ->willReturn(['guid1']);

        $result = $this->service->enrichItemsWithReadStatus([], $userId);

        $this->assertEmpty($result);
    }
}
