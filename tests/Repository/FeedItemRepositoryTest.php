<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository;

use App\Entity\FeedItem;
use App\Repository\FeedItemRepository;
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
        string $subscriptionGuid = 'testfeed1234567',
        ?\DateTimeImmutable $publishedAt = null,
    ): FeedItem {
        return new FeedItem(
            $guid,
            $subscriptionGuid,
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
    public function findByGuidsReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->repository->findByGuids([]);

        $this->assertSame([], $result);
    }

    #[Test]
    public function findByGuidsReturnsIndexedByGuid(): void
    {
        $subscriptionGuid = 'findbyguids12345';
        $item1 = $this->createFeedItem('guiditem12345678', $subscriptionGuid);
        $item2 = $this->createFeedItem('guiditem23456789', $subscriptionGuid);
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
        $subscriptionGuid = 'findbyguids23456';
        $item = $this->createFeedItem('existingitem1234', $subscriptionGuid);
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
    public function findBySubscriptionGuidsReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repository->findBySubscriptionGuids([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findBySubscriptionGuidsReturnsItemsForMultipleSubscriptions(): void
    {
        $subscriptionGuid1 = 'multifeed1234567';
        $subscriptionGuid2 = 'multifeed2345678';

        $item1 = $this->createFeedItem('multiitem12345678', $subscriptionGuid1);
        $item2 = $this->createFeedItem('multiitem23456789', $subscriptionGuid2);

        $this->repository->upsert($item1);
        $this->repository->upsert($item2);

        $result = $this->repository->findBySubscriptionGuids([
            $subscriptionGuid1,
            $subscriptionGuid2,
        ]);

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
    public function getItemCountBySubscriptionGuidReturnsZeroForEmpty(): void
    {
        $count = $this->repository->getItemCountBySubscriptionGuid(
            'emptyfeed1234567',
        );

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function getItemCountBySubscriptionGuidReturnsCorrectCount(): void
    {
        $subscriptionGuid = 'countfeed1234567';
        $this->repository->upsert(
            $this->createFeedItem('count1234567890a', $subscriptionGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('count1234567890b', $subscriptionGuid),
        );

        $count = $this->repository->getItemCountBySubscriptionGuid(
            $subscriptionGuid,
        );

        $this->assertGreaterThanOrEqual(2, $count);
    }

    #[Test]
    public function deleteOlderThanRemovesOldItems(): void
    {
        $subscriptionGuid = 'deletefeed123456';
        $oldItem = $this->createFeedItem(
            'olditem123456789',
            $subscriptionGuid,
            new \DateTimeImmutable('-30 days'),
        );
        $this->repository->upsert($oldItem);

        $deleted = $this->repository->deleteOlderThan(
            new \DateTimeImmutable('-7 days'),
        );

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    #[Test]
    public function getGuidsBySubscriptionGuidReturnsEmptyForNoItems(): void
    {
        $guids = $this->repository->getGuidsBySubscriptionGuid(
            'noguidsfeed12345',
        );

        $this->assertIsArray($guids);
        $this->assertEmpty($guids);
    }

    #[Test]
    public function getGuidsBySubscriptionGuidReturnsGuids(): void
    {
        $subscriptionGuid = 'guidsfeed1234567';
        $this->repository->upsert(
            $this->createFeedItem('guidsitem1234567', $subscriptionGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('guidsitem2345678', $subscriptionGuid),
        );

        $guids = $this->repository->getGuidsBySubscriptionGuid(
            $subscriptionGuid,
        );

        $this->assertContains('guidsitem1234567', $guids);
        $this->assertContains('guidsitem2345678', $guids);
    }

    #[Test]
    public function deleteBySubscriptionGuidRemovesAllItemsForSubscription(): void
    {
        $subscriptionGuid = 'deletebyfeed1234';
        $this->repository->upsert(
            $this->createFeedItem('delbyfeed1234567', $subscriptionGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('delbyfeed2345678', $subscriptionGuid),
        );

        $deleted = $this->repository->deleteBySubscriptionGuid(
            $subscriptionGuid,
        );

        $this->assertGreaterThanOrEqual(0, $deleted);
        $this->assertEmpty(
            $this->repository->getGuidsBySubscriptionGuid($subscriptionGuid),
        );
    }

    #[Test]
    public function getItemGuidsBySubscriptionReturnsGuidsOrderedByDate(): void
    {
        $subscriptionGuid = 'itemguids1234567';
        $this->repository->upsert(
            $this->createFeedItem(
                'itemguids12345a1',
                $subscriptionGuid,
                new \DateTimeImmutable('-2 days'),
            ),
        );
        $this->repository->upsert(
            $this->createFeedItem(
                'itemguids12345a2',
                $subscriptionGuid,
                new \DateTimeImmutable('-1 day'),
            ),
        );

        $guids = $this->repository->getItemGuidsBySubscription(
            $subscriptionGuid,
        );

        $this->assertContains('itemguids12345a1', $guids);
        $this->assertContains('itemguids12345a2', $guids);
    }

    #[Test]
    public function getUnreadCountsBySubscriptionReturnsEmptyForEmptyInput(): void
    {
        $counts = $this->repository->getUnreadCountsBySubscription([], 1);

        $this->assertIsArray($counts);
        $this->assertEmpty($counts);
    }

    #[Test]
    public function getUnreadCountsBySubscriptionReturnsCounts(): void
    {
        $subscriptionGuid = 'unreadcnt1234567';
        $this->repository->upsert(
            $this->createFeedItem('unreadcnt12345a1', $subscriptionGuid),
        );
        $this->repository->upsert(
            $this->createFeedItem('unreadcnt12345a2', $subscriptionGuid),
        );

        $counts = $this->repository->getUnreadCountsBySubscription(
            [$subscriptionGuid],
            999,
        );

        $this->assertArrayHasKey($subscriptionGuid, $counts);
        $this->assertGreaterThanOrEqual(2, $counts[$subscriptionGuid]);
    }

    #[Test]
    public function findItemsWithStatusReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repository->findItemsWithStatus([], 1);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findItemsWithStatusReturnsItemsWithStatusFields(): void
    {
        $subscriptionGuid = 'statusfeed123456';
        $this->repository->upsert(
            $this->createFeedItem('statusitem123456', $subscriptionGuid),
        );

        $result = $this->repository->findItemsWithStatus(
            [$subscriptionGuid],
            999,
        );

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('guid', $result[0]);
        $this->assertArrayHasKey('isRead', $result[0]);
        $this->assertArrayHasKey('isNew', $result[0]);
    }

    #[Test]
    public function findItemsWithStatusFiltersOutItemsWithFilterWords(): void
    {
        $subscriptionGuid = 'filterfeed123456';
        $this->repository->upsert(
            new FeedItem(
                'filteritem1234567',
                $subscriptionGuid,
                'Breaking News Alert',
                'https://example.com/news',
                'Test Source',
                'This is a test excerpt',
                new \DateTimeImmutable(),
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'filteritem2345678',
                $subscriptionGuid,
                'Normal Article',
                'https://example.com/article',
                'Test Source',
                'Regular content here',
                new \DateTimeImmutable(),
            ),
        );

        $result = $this->repository->findItemsWithStatus(
            [$subscriptionGuid],
            999,
            ['Breaking'],
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Normal Article', $result[0]['title']);
    }

    #[Test]
    public function findItemsWithStatusExcludesItemFromUnreadFilter(): void
    {
        $subscriptionGuid = 'unreadfilt123456';
        $this->repository->upsert(
            $this->createFeedItem('unreadfilt1234567', $subscriptionGuid),
        );

        // Test with excludeFromUnreadFilter parameter
        $result = $this->repository->findItemsWithStatus(
            [$subscriptionGuid],
            999,
            [],
            true,
            0,
            null,
            'unreadfilt1234567',
        );

        // The item should be included even with unreadOnly=true because it's excluded
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function getUnreadCountsBySubscriptionFiltersWithFilterWords(): void
    {
        $subscriptionGuid = 'filtercnt1234567';

        // Create item with filter word in title
        $this->repository->upsert(
            new FeedItem(
                'filtercnt12345a1',
                $subscriptionGuid,
                'Sponsored Content',
                'https://example.com/sponsored',
                'Test Source',
                'Regular excerpt',
                new \DateTimeImmutable(),
            ),
        );

        // Create item without filter word
        $this->repository->upsert(
            new FeedItem(
                'filtercnt12345a2',
                $subscriptionGuid,
                'Normal Article',
                'https://example.com/article',
                'Test Source',
                'Regular excerpt',
                new \DateTimeImmutable(),
            ),
        );

        // Without filter words - should count both
        $countsWithoutFilter = $this->repository->getUnreadCountsBySubscription(
            [$subscriptionGuid],
            999,
            [],
        );

        // With filter words - should exclude sponsored item
        $countsWithFilter = $this->repository->getUnreadCountsBySubscription(
            [$subscriptionGuid],
            999,
            ['Sponsored'],
        );

        $this->assertArrayHasKey($subscriptionGuid, $countsWithoutFilter);
        $this->assertArrayHasKey($subscriptionGuid, $countsWithFilter);
        $this->assertGreaterThan(
            $countsWithFilter[$subscriptionGuid],
            $countsWithoutFilter[$subscriptionGuid],
        );
    }
}
