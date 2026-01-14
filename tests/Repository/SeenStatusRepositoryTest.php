<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository;

use App\Repository\SeenStatusRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SeenStatusRepositoryTest extends KernelTestCase
{
    private SeenStatusRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(
            SeenStatusRepository::class,
        );
    }

    #[Test]
    public function deleteByFeedItemGuidsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->deleteByFeedItemGuids(1, []);

        $this->assertEquals(0, $result);
    }

    #[Test]
    public function markAsSeenIsIdempotent(): void
    {
        $userId = 1;
        $feedItemGuid = 'test-guid-idempotent';

        $this->repository->markAsSeen($userId, $feedItemGuid);
        $this->repository->markAsSeen($userId, $feedItemGuid);

        $this->assertTrue($this->repository->isSeen($userId, $feedItemGuid));
    }

    #[Test]
    public function markAsSeenCreatesRecord(): void
    {
        $userId = 1;
        $feedItemGuid = 'test-guid-new';

        $this->assertFalse($this->repository->isSeen($userId, $feedItemGuid));

        $this->repository->markAsSeen($userId, $feedItemGuid);

        $this->assertTrue($this->repository->isSeen($userId, $feedItemGuid));
    }

    #[Test]
    public function markManyAsSeenWithEmptyArrayDoesNothing(): void
    {
        $this->repository->markManyAsSeen(1, []);

        $this->assertTrue(true);
    }

    #[Test]
    public function markManyAsSeenCreatesRecords(): void
    {
        $userId = 1;
        $guids = ['batch-guid-1', 'batch-guid-2', 'batch-guid-3'];

        foreach ($guids as $guid) {
            $this->assertFalse($this->repository->isSeen($userId, $guid));
        }

        $this->repository->markManyAsSeen($userId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->repository->isSeen($userId, $guid));
        }
    }

    #[Test]
    public function markManyAsSeenIsIdempotent(): void
    {
        $userId = 1;
        $guids = ['batch-idem-1', 'batch-idem-2'];

        $this->repository->markManyAsSeen($userId, $guids);
        $this->repository->markManyAsSeen($userId, $guids);

        foreach ($guids as $guid) {
            $this->assertTrue($this->repository->isSeen($userId, $guid));
        }
    }
}
