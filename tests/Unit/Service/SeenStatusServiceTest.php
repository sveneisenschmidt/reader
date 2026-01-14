<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Repository\SeenStatusRepository;
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
}
