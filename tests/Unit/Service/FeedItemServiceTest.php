<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\Feed\Entity\FeedItem;
use App\Domain\Feed\Repository\FeedItemRepository;
use App\Domain\Feed\Service\FeedItemService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedItemServiceTest extends TestCase
{
    #[Test]
    public function findByGuidReturnsRepositoryResult(): void
    {
        $guid = 'item-guid';
        $feedItem = new FeedItem(
            guid: $guid,
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com',
            source: 'Test Source',
            excerpt: 'Test excerpt',
            publishedAt: new \DateTimeImmutable(),
        );

        $repository = $this->createStub(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn($feedItem);

        $service = new FeedItemService($repository);

        $this->assertSame($feedItem, $service->findByGuid($guid));
    }

    #[Test]
    public function findByGuidReturnsNullWhenNotFound(): void
    {
        $guid = 'non-existent-guid';

        $repository = $this->createStub(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn(null);

        $service = new FeedItemService($repository);

        $this->assertNull($service->findByGuid($guid));
    }
}
