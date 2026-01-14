<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Repository\ReadStatusRepository;
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
}
