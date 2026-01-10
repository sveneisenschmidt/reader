<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Users;

use App\Repository\Users\SeenStatusRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SeenStatusRepositoryTest extends KernelTestCase
{
    private SeenStatusRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(SeenStatusRepository::class);
    }

    #[Test]
    public function deleteByFeedItemGuidsWithEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->deleteByFeedItemGuids(1, []);

        $this->assertEquals(0, $result);
    }
}
