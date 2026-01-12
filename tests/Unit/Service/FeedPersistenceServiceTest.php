<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Content\FeedItem;
use App\Repository\Content\FeedItemRepository;
use App\Service\FeedPersistenceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedPersistenceServiceTest extends TestCase
{
    #[Test]
    public function persistFeedItemsCreatesNewItems(): void
    {
        $repository = $this->createMock(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(FeedItem::class));
        $entityManager->expects($this->once())->method('flush');

        $service = $this->createService($repository, $entityManager);

        $items = [
            [
                'guid' => 'item-1',
                'feedGuid' => 'feed-1',
                'title' => 'Test Item',
                'link' => 'https://example.com/item',
                'source' => 'Test Feed',
                'excerpt' => 'Item excerpt',
                'date' => new \DateTimeImmutable('2024-01-01'),
            ],
        ];

        $service->persistFeedItems($items);
    }

    #[Test]
    public function persistFeedItemsUpdatesRecentExistingItems(): void
    {
        $existingItem = $this->createMock(FeedItem::class);
        $existingItem
            ->method('getPublishedAt')
            ->willReturn(new \DateTimeImmutable('-1 day'));
        $existingItem
            ->expects($this->once())
            ->method('setTitle')
            ->with('Updated Title');
        $existingItem
            ->expects($this->once())
            ->method('setLink')
            ->with('https://example.com/updated');
        $existingItem
            ->expects($this->once())
            ->method('setSource')
            ->with('Updated Source');
        $existingItem
            ->expects($this->once())
            ->method('setExcerpt')
            ->with('Updated excerpt');

        $repository = $this->createMock(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn($existingItem);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = $this->createService($repository, $entityManager);

        $items = [
            [
                'guid' => 'item-1',
                'feedGuid' => 'feed-1',
                'title' => 'Updated Title',
                'link' => 'https://example.com/updated',
                'source' => 'Updated Source',
                'excerpt' => 'Updated excerpt',
                'date' => new \DateTimeImmutable(),
            ],
        ];

        $service->persistFeedItems($items);
    }

    #[Test]
    public function persistFeedItemsSkipsOldExistingItems(): void
    {
        $existingItem = $this->createMock(FeedItem::class);
        $existingItem
            ->method('getPublishedAt')
            ->willReturn(new \DateTimeImmutable('-3 days'));
        $existingItem->expects($this->never())->method('setTitle');

        $repository = $this->createMock(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn($existingItem);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = $this->createService($repository, $entityManager);

        $items = [
            [
                'guid' => 'item-1',
                'feedGuid' => 'feed-1',
                'title' => 'Updated Title',
                'link' => 'https://example.com/updated',
                'source' => 'Updated Source',
                'excerpt' => 'Updated excerpt',
                'date' => new \DateTimeImmutable(),
            ],
        ];

        $service->persistFeedItems($items);
    }

    #[Test]
    public function persistFeedItemsHandlesDateTimeObject(): void
    {
        $repository = $this->createMock(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = $this->createService($repository, $entityManager);

        $items = [
            [
                'guid' => 'item-1',
                'feedGuid' => 'feed-1',
                'title' => 'Test Item',
                'link' => 'https://example.com/item',
                'source' => 'Test Feed',
                'excerpt' => 'Item excerpt',
                'date' => new \DateTime('2024-01-01'),
            ],
        ];

        $service->persistFeedItems($items);
    }

    #[Test]
    public function persistFeedItemsHandlesNullDate(): void
    {
        $repository = $this->createMock(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = $this->createService($repository, $entityManager);

        $items = [
            [
                'guid' => 'item-1',
                'feedGuid' => 'feed-1',
                'title' => 'Test Item',
                'link' => 'https://example.com/item',
                'source' => 'Test Feed',
                'excerpt' => 'Item excerpt',
                'date' => null,
            ],
        ];

        $service->persistFeedItems($items);
    }

    #[Test]
    public function getAllItemsReturnsMappedItems(): void
    {
        $feedItem = $this->createMock(FeedItem::class);
        $feedItem
            ->method('toArray')
            ->willReturn(['guid' => 'item-1', 'title' => 'Test']);

        $repository = $this->createMock(FeedItemRepository::class);
        $repository
            ->method('findByFeedGuids')
            ->with(['feed-1', 'feed-2'])
            ->willReturn([$feedItem]);

        $service = $this->createService($repository);

        $result = $service->getAllItems(['feed-1', 'feed-2']);

        $this->assertCount(1, $result);
        $this->assertEquals('item-1', $result[0]['guid']);
    }

    #[Test]
    public function getItemByGuidReturnsItemArray(): void
    {
        $feedItem = $this->createMock(FeedItem::class);
        $feedItem
            ->method('toArray')
            ->willReturn(['guid' => 'item-1', 'title' => 'Test']);

        $repository = $this->createMock(FeedItemRepository::class);
        $repository
            ->method('findByGuid')
            ->with('item-1')
            ->willReturn($feedItem);

        $service = $this->createService($repository);

        $result = $service->getItemByGuid('item-1');

        $this->assertEquals('item-1', $result['guid']);
    }

    #[Test]
    public function getItemByGuidReturnsNullIfNotFound(): void
    {
        $repository = $this->createMock(FeedItemRepository::class);
        $repository->method('findByGuid')->willReturn(null);

        $service = $this->createService($repository);

        $result = $service->getItemByGuid('nonexistent');

        $this->assertNull($result);
    }

    #[Test]
    public function getItemCountForFeedReturnsCount(): void
    {
        $repository = $this->createMock(FeedItemRepository::class);
        $repository
            ->expects($this->once())
            ->method('getItemCountByFeedGuid')
            ->with('feed-1')
            ->willReturn(42);

        $service = $this->createService($repository);

        $count = $service->getItemCountForFeed('feed-1');

        $this->assertEquals(42, $count);
    }

    private function createService(
        ?FeedItemRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
    ): FeedPersistenceService {
        return new FeedPersistenceService(
            $repository ?? $this->createStub(FeedItemRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
        );
    }
}
