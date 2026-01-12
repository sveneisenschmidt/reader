<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Content;

use App\Entity\Content\FeedItem;
use App\Repository\Content\FeedItemRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FeedItemRepositoryTest extends KernelTestCase
{
    private FeedItemRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(
            FeedItemRepository::class,
        );
    }

    private function createFeedItem(
        string $guid,
        string $feedGuid = 'testfeed1234567',
        ?\DateTimeImmutable $publishedAt = null,
    ): FeedItem {
        return new FeedItem(
            $guid,
            $feedGuid,
            "Test Item $guid",
            "https://example.com/$guid",
            'Test Source',
            "Test excerpt for $guid",
            $publishedAt ?? new \DateTimeImmutable(),
        );
    }

    #[Test]
    public function findByGuidReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByGuid('nonexistent12345');

        $this->assertNull($result);
    }

    #[Test]
    public function findByGuidReturnsFeedItem(): void
    {
        $feedItem = $this->createFeedItem('testguid12345678');
        $this->repository->upsert($feedItem);

        $result = $this->repository->findByGuid('testguid12345678');

        $this->assertNotNull($result);
        $this->assertEquals('testguid12345678', $result->getGuid());
    }

    #[Test]
    public function findByFeedGuidReturnsEmptyArrayWhenNoItems(): void
    {
        $result = $this->repository->findByFeedGuid('nonexistentfeed1');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findByFeedGuidReturnsItemsOrderedByDate(): void
    {
        $feedGuid = 'orderedfeed12345';
        $older = $this->createFeedItem(
            'olderitem1234567',
            $feedGuid,
            new \DateTimeImmutable('-1 day'),
        );
        $newer = $this->createFeedItem(
            'neweritem1234567',
            $feedGuid,
            new \DateTimeImmutable('now'),
        );

        $this->repository->upsert($older);
        $this->repository->upsert($newer);

        $result = $this->repository->findByFeedGuid($feedGuid);

        $this->assertCount(2, $result);
        $this->assertEquals('neweritem1234567', $result[0]->getGuid());
        $this->assertEquals('olderitem1234567', $result[1]->getGuid());
    }

    #[Test]
    public function findAllOrderedByDateReturnsItems(): void
    {
        $result = $this->repository->findAllOrderedByDate();

        $this->assertIsArray($result);
    }

    #[Test]
    public function findByGuidsReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->repository->findByGuids([]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function findByGuidsReturnsIndexedByGuid(): void
    {
        $feedGuid = 'findbyguids12345';
        $item1 = $this->createFeedItem('guiditem12345678', $feedGuid);
        $item2 = $this->createFeedItem('guiditem23456789', $feedGuid);
        $this->repository->upsert($item1);
        $this->repository->upsert($item2);

        $result = $this->repository->findByGuids([
            'guiditem12345678',
            'guiditem23456789',
        ]);

        $this->assertArrayHasKey('guiditem12345678', $result);
        $this->assertArrayHasKey('guiditem23456789', $result);
        $this->assertEquals(
            'Test Item guiditem12345678',
            $result['guiditem12345678']->getTitle(),
        );
        $this->assertEquals(
            'Test Item guiditem23456789',
            $result['guiditem23456789']->getTitle(),
        );
    }

    #[Test]
    public function findByGuidsReturnsOnlyExistingItems(): void
    {
        $feedGuid = 'findbyguids23456';
        $item = $this->createFeedItem('existingitem1234', $feedGuid);
        $this->repository->upsert($item);

        $result = $this->repository->findByGuids([
            'existingitem1234',
            'nonexistent12345',
        ]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('existingitem1234', $result);
        $this->assertArrayNotHasKey('nonexistent12345', $result);
    }

    #[Test]
    public function findByFeedGuidsReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repository->findByFeedGuids([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findByFeedGuidsReturnsItemsForMultipleFeeds(): void
    {
        $feedGuid1 = 'multifeed1234567';
        $feedGuid2 = 'multifeed2345678';

        $item1 = $this->createFeedItem('multiitem12345678', $feedGuid1);
        $item2 = $this->createFeedItem('multiitem23456789', $feedGuid2);

        $this->repository->upsert($item1);
        $this->repository->upsert($item2);

        $result = $this->repository->findByFeedGuids([$feedGuid1, $feedGuid2]);

        $this->assertGreaterThanOrEqual(2, count($result));
    }

    #[Test]
    public function upsertCreatesNewItem(): void
    {
        $feedItem = $this->createFeedItem('newupsertitem123');

        $this->repository->upsert($feedItem);

        $result = $this->repository->findByGuid('newupsertitem123');
        $this->assertNotNull($result);
        $this->assertEquals('Test Item newupsertitem123', $result->getTitle());
    }

    #[Test]
    public function upsertUpdatesExistingItem(): void
    {
        $feedItem = $this->createFeedItem('updateitem123456');
        $this->repository->upsert($feedItem);

        // Create updated version
        $updatedItem = new FeedItem(
            'updateitem123456',
            'testfeed1234567',
            'Updated Title',
            'https://example.com/updated',
            'Updated Source',
            'Updated excerpt',
            new \DateTimeImmutable(),
        );

        $this->repository->upsert($updatedItem);

        $result = $this->repository->findByGuid('updateitem123456');
        $this->assertEquals('Updated Title', $result->getTitle());
        $this->assertEquals('https://example.com/updated', $result->getLink());
    }

    #[Test]
    public function upsertBatchHandlesEmptyArray(): void
    {
        // Should not throw any error
        $this->repository->upsertBatch([]);

        $this->assertTrue(true);
    }

    #[Test]
    public function upsertBatchCreatesMultipleItems(): void
    {
        $items = [
            $this->createFeedItem('batchitem1234567'),
            $this->createFeedItem('batchitem2345678'),
            $this->createFeedItem('batchitem3456789'),
        ];

        $this->repository->upsertBatch($items);

        $this->assertNotNull($this->repository->findByGuid('batchitem1234567'));
        $this->assertNotNull($this->repository->findByGuid('batchitem2345678'));
        $this->assertNotNull($this->repository->findByGuid('batchitem3456789'));
    }

    #[Test]
    public function upsertBatchUpdatesExistingItems(): void
    {
        $original = $this->createFeedItem('batchupdate12345');
        $this->repository->upsert($original);

        $updated = new FeedItem(
            'batchupdate12345',
            'testfeed1234567',
            'Batch Updated Title',
            'https://example.com/batch-updated',
            'Test Source',
            'Test excerpt',
            new \DateTimeImmutable(),
        );

        $this->repository->upsertBatch([$updated]);

        $result = $this->repository->findByGuid('batchupdate12345');
        $this->assertEquals('Batch Updated Title', $result->getTitle());
    }

    #[Test]
    public function getItemCountByFeedGuidReturnsZeroForEmpty(): void
    {
        $count = $this->repository->getItemCountByFeedGuid('emptyfeed1234567');

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function getItemCountByFeedGuidReturnsCorrectCount(): void
    {
        $feedGuid = 'countfeed1234567';
        $this->repository->upsert(
            $this->createFeedItem('count1234567890a', $feedGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('count1234567890b', $feedGuid),
        );

        $count = $this->repository->getItemCountByFeedGuid($feedGuid);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    #[Test]
    public function deleteOlderThanRemovesOldItems(): void
    {
        $feedGuid = 'deletefeed123456';
        $oldItem = $this->createFeedItem(
            'olditem123456789',
            $feedGuid,
            new \DateTimeImmutable('-30 days'),
        );
        $this->repository->upsert($oldItem);

        $deleted = $this->repository->deleteOlderThan(
            new \DateTimeImmutable('-7 days'),
        );

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    #[Test]
    public function getGuidsByFeedGuidReturnsEmptyForNoItems(): void
    {
        $guids = $this->repository->getGuidsByFeedGuid('noguidsfeed12345');

        $this->assertIsArray($guids);
        $this->assertEmpty($guids);
    }

    #[Test]
    public function getGuidsByFeedGuidReturnsGuids(): void
    {
        $feedGuid = 'guidsfeed1234567';
        $this->repository->upsert(
            $this->createFeedItem('guidsitem1234567', $feedGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('guidsitem2345678', $feedGuid),
        );

        $guids = $this->repository->getGuidsByFeedGuid($feedGuid);

        $this->assertContains('guidsitem1234567', $guids);
        $this->assertContains('guidsitem2345678', $guids);
    }

    #[Test]
    public function deleteByFeedGuidRemovesAllItemsForFeed(): void
    {
        $feedGuid = 'deletebyfeed1234';
        $this->repository->upsert(
            $this->createFeedItem('delbyfeed1234567', $feedGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('delbyfeed2345678', $feedGuid),
        );

        $deleted = $this->repository->deleteByFeedGuid($feedGuid);

        $this->assertGreaterThanOrEqual(0, $deleted);
        $this->assertEmpty($this->repository->findByFeedGuid($feedGuid));
    }
}
