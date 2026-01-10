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
use PHPUnit\Framework\TestCase;

class ReadStatusServiceTest extends TestCase
{
    #[Test]
    public function markAsReadDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createMock(ReadStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('markAsRead')
            ->with($userId, $guid);

        $service = new ReadStatusService($repository);
        $service->markAsRead($userId, $guid);
    }

    #[Test]
    public function markAsUnreadDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createMock(ReadStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('markAsUnread')
            ->with($userId, $guid);

        $service = new ReadStatusService($repository);
        $service->markAsUnread($userId, $guid);
    }

    #[Test]
    public function markManyAsReadDelegatesToRepository(): void
    {
        $userId = 1;
        $guids = ['guid1', 'guid2', 'guid3'];

        $repository = $this->createMock(ReadStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('markManyAsRead')
            ->with($userId, $guids);

        $service = new ReadStatusService($repository);
        $service->markManyAsRead($userId, $guids);
    }

    #[Test]
    public function isReadReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createStub(ReadStatusRepository::class);
        $repository->method('isRead')->willReturn(true);

        $service = new ReadStatusService($repository);

        $this->assertTrue($service->isRead($userId, $guid));
    }

    #[Test]
    public function getReadGuidsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guids = ['guid1', 'guid2'];

        $repository = $this->createStub(ReadStatusRepository::class);
        $repository->method('getReadGuidsForUser')->willReturn($guids);

        $service = new ReadStatusService($repository);
        $result = $service->getReadGuidsForUser($userId);

        $this->assertEquals($guids, $result);
    }

    #[Test]
    public function enrichItemsWithReadStatusMarksReadItems(): void
    {
        $userId = 1;
        $readGuids = ['guid1', 'guid3'];

        $repository = $this->createStub(ReadStatusRepository::class);
        $repository->method('getReadGuidsForUser')->willReturn($readGuids);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
            ['guid' => 'guid3', 'title' => 'Item 3'],
        ];

        $service = new ReadStatusService($repository);
        $result = $service->enrichItemsWithReadStatus($items, $userId);

        $this->assertTrue($result[0]['isRead']);
        $this->assertFalse($result[1]['isRead']);
        $this->assertTrue($result[2]['isRead']);
    }

    #[Test]
    public function enrichItemsWithReadStatusHandlesEmptyReadList(): void
    {
        $userId = 1;

        $repository = $this->createStub(ReadStatusRepository::class);
        $repository->method('getReadGuidsForUser')->willReturn([]);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
        ];

        $service = new ReadStatusService($repository);
        $result = $service->enrichItemsWithReadStatus($items, $userId);

        $this->assertFalse($result[0]['isRead']);
        $this->assertFalse($result[1]['isRead']);
    }

    #[Test]
    public function enrichItemsWithReadStatusHandlesEmptyItemsList(): void
    {
        $userId = 1;

        $repository = $this->createStub(ReadStatusRepository::class);
        $repository->method('getReadGuidsForUser')->willReturn(['guid1']);

        $service = new ReadStatusService($repository);
        $result = $service->enrichItemsWithReadStatus([], $userId);

        $this->assertEmpty($result);
    }
}
