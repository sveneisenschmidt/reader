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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SeenStatusServiceTest extends TestCase
{
    private SeenStatusRepository&MockObject $seenStatusRepository;
    private SeenStatusService $service;

    protected function setUp(): void
    {
        $this->seenStatusRepository = $this->createMock(SeenStatusRepository::class);
        $this->service = new SeenStatusService($this->seenStatusRepository);
    }

    #[Test]
    public function markAsSeenDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $this->seenStatusRepository
            ->expects($this->once())
            ->method('markAsSeen')
            ->with($userId, $guid);

        $this->service->markAsSeen($userId, $guid);
    }

    #[Test]
    public function markManyAsSeenDelegatesToRepository(): void
    {
        $userId = 1;
        $guids = ['guid1', 'guid2', 'guid3'];

        $this->seenStatusRepository
            ->expects($this->once())
            ->method('markManyAsSeen')
            ->with($userId, $guids);

        $this->service->markManyAsSeen($userId, $guids);
    }

    #[Test]
    public function isSeenReturnsRepositoryResult(): void
    {
        $userId = 1;
        $guid = 'item-guid';

        $this->seenStatusRepository
            ->method('isSeen')
            ->with($userId, $guid)
            ->willReturn(true);

        $this->assertTrue($this->service->isSeen($userId, $guid));
    }

    #[Test]
    public function getSeenGuidsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $filterGuids = ['guid1', 'guid2'];
        $seenGuids = ['guid1'];

        $this->seenStatusRepository
            ->method('getSeenGuidsForUser')
            ->with($userId, $filterGuids)
            ->willReturn($seenGuids);

        $result = $this->service->getSeenGuidsForUser($userId, $filterGuids);

        $this->assertEquals($seenGuids, $result);
    }

    #[Test]
    public function enrichItemsWithSeenStatusMarksNewItems(): void
    {
        $userId = 1;

        $this->seenStatusRepository
            ->method('getSeenGuidsForUser')
            ->willReturn(['guid1', 'guid3']);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
            ['guid' => 'guid3', 'title' => 'Item 3'],
        ];

        $result = $this->service->enrichItemsWithSeenStatus($items, $userId);

        $this->assertFalse($result[0]['isNew']); // seen = not new
        $this->assertTrue($result[1]['isNew']);  // not seen = new
        $this->assertFalse($result[2]['isNew']); // seen = not new
    }

    #[Test]
    public function enrichItemsWithSeenStatusHandlesEmptySeenList(): void
    {
        $userId = 1;

        $this->seenStatusRepository
            ->method('getSeenGuidsForUser')
            ->willReturn([]);

        $items = [
            ['guid' => 'guid1', 'title' => 'Item 1'],
            ['guid' => 'guid2', 'title' => 'Item 2'],
        ];

        $result = $this->service->enrichItemsWithSeenStatus($items, $userId);

        $this->assertTrue($result[0]['isNew']);
        $this->assertTrue($result[1]['isNew']);
    }

    #[Test]
    public function enrichItemsWithSeenStatusHandlesEmptyItemsList(): void
    {
        $userId = 1;

        $this->seenStatusRepository
            ->method('getSeenGuidsForUser')
            ->willReturn([]);

        $result = $this->service->enrichItemsWithSeenStatus([], $userId);

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

        $this->seenStatusRepository
            ->expects($this->once())
            ->method('getSeenGuidsForUser')
            ->with($userId, ['guid1', 'guid2'])
            ->willReturn([]);

        $this->service->enrichItemsWithSeenStatus($items, $userId);
    }
}
