<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Repository\Users\SeenStatusRepository;
use App\Service\SeenStatusService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SeenStatusServiceTest extends TestCase
{
    #[Test]
    public function markAsSeenDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createMock(SeenStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('markAsSeen')
            ->with($userId, $guid);

        $service = new SeenStatusService($repository);
        $service->markAsSeen($userId, $guid);
    }

    #[Test]
    public function markManyAsSeenDelegatesToRepository(): void
    {
        $userId = 1;
        $guids = ['guid1', 'guid2', 'guid3'];

        $repository = $this->createMock(SeenStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('markManyAsSeen')
            ->with($userId, $guids);

        $service = new SeenStatusService($repository);
        $service->markManyAsSeen($userId, $guids);
    }

    #[Test]
    public function isSeenReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $repository = $this->createStub(SeenStatusRepository::class);
        $repository->method('isSeen')->willReturn(true);

        $service = new SeenStatusService($repository);

        $this->assertTrue($service->isSeen($userId, $guid));
    }

    #[Test]
    public function getSeenGuidsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $filterGuids = ['guid1', 'guid2'];
        $seenGuids = ['guid1'];

        $repository = $this->createStub(SeenStatusRepository::class);
        $repository->method('getSeenGuidsForUser')->willReturn($seenGuids);

        $service = new SeenStatusService($repository);
        $result = $service->getSeenGuidsForUser($userId, $filterGuids);

        $this->assertEquals($seenGuids, $result);
    }

    #[Test]
    public function enrichItemsWithSeenStatusMarksNewItems(): void
    {
        $userId = 1;

        $repository = $this->createStub(SeenStatusRepository::class);
        $repository
            ->method('getSeenGuidsForUser')
            ->willReturn(['guid1', 'guid3']);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
            ['guid' => 'guid3', 'title' => 'Item 3'],
        ];

        $service = new SeenStatusService($repository);
        $result = $service->enrichItemsWithSeenStatus($items, $userId);

        $this->assertFalse($result[0]['isNew']); // seen = not new
        $this->assertTrue($result[1]['isNew']); // not seen = new
        $this->assertFalse($result[2]['isNew']); // seen = not new
    }

    #[Test]
    public function enrichItemsWithSeenStatusHandlesEmptySeenList(): void
    {
        $userId = 1;

        $repository = $this->createStub(SeenStatusRepository::class);
        $repository->method('getSeenGuidsForUser')->willReturn([]);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
        ];

        $service = new SeenStatusService($repository);
        $result = $service->enrichItemsWithSeenStatus($items, $userId);

        $this->assertTrue($result[0]['isNew']);
        $this->assertTrue($result[1]['isNew']);
    }

    #[Test]
    public function enrichItemsWithSeenStatusHandlesEmptyItemsList(): void
    {
        $userId = 1;

        $repository = $this->createStub(SeenStatusRepository::class);
        $repository->method('getSeenGuidsForUser')->willReturn([]);

        $service = new SeenStatusService($repository);
        $result = $service->enrichItemsWithSeenStatus([], $userId);

        $this->assertEmpty($result);
    }

    #[Test]
    public function enrichItemsExtractsGuidsForFiltering(): void
    {
        $userId = 1;
        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
        ];

        $repository = $this->createMock(SeenStatusRepository::class);
        $repository
            ->expects($this->once())
            ->method('getSeenGuidsForUser')
            ->with($userId, ['guid1', 'guid2'])
            ->willReturn([]);

        $service = new SeenStatusService($repository);
        $service->enrichItemsWithSeenStatus($items, $userId);
    }
}
