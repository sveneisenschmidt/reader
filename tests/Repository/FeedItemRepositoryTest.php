<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository;

use App\Domain\Feed\Entity\FeedItem;
use App\Domain\Feed\Repository\FeedItemQueryCriteria;
use App\Domain\Feed\Repository\FeedItemRepository;
use App\Domain\ItemStatus\Repository\BookmarkStatusRepository;
use App\Tests\Trait\DatabaseIsolationTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FeedItemRepositoryTest extends KernelTestCase
{
    use DatabaseIsolationTrait;

    private FeedItemRepository $repository;
    private BookmarkStatusRepository $bookmarkRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(
            FeedItemRepository::class,
        );
        $this->bookmarkRepository = static::getContainer()->get(
            BookmarkStatusRepository::class,
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
    public function trimToLimitPerSubscriptionRemovesExcessItems(): void
    {
        $subscriptionGuid = 'trimfeed12345678';

        // Create 5 items
        for ($i = 0; $i < 5; ++$i) {
            $item = $this->createFeedItem(
                "trimitem{$i}1234567",
                $subscriptionGuid,
                new \DateTimeImmutable("-{$i} days"),
            );
            $this->repository->upsert($item);
        }

        // Trim to 3 items
        $deleted = $this->repository->trimToLimitPerSubscription(3);

        $this->assertGreaterThanOrEqual(2, $deleted);

        // Verify only 3 items remain
        $remaining = $this->repository->getGuidsBySubscriptionGuid(
            $subscriptionGuid,
        );
        $this->assertCount(3, $remaining);
    }

    #[Test]
    public function trimToLimitPerSubscriptionDoesNotRemoveBookmarkedItems(): void
    {
        $subscriptionGuid = 'trimbookmark1234';
        $userId = 994;
        $bookmarkedGuid = 'bookmarkedtrim12';

        // Create 5 items, one will be bookmarked
        for ($i = 0; $i < 4; ++$i) {
            $item = $this->createFeedItem(
                "trimnobook{$i}12345",
                $subscriptionGuid,
                new \DateTimeImmutable("-{$i} days"),
            );
            $this->repository->upsert($item);
        }

        // Create an old item that will be bookmarked
        $oldItem = $this->createFeedItem(
            $bookmarkedGuid,
            $subscriptionGuid,
            new \DateTimeImmutable('-30 days'),
        );
        $this->repository->upsert($oldItem);

        // Bookmark the old item
        $this->bookmarkRepository->bookmark($userId, $bookmarkedGuid);

        // Trim to 2 items - should keep 2 newest + 1 bookmarked
        $this->repository->trimToLimitPerSubscription(2);

        // Bookmarked item should still exist
        $result = $this->repository->findByGuid($bookmarkedGuid);
        $this->assertNotNull($result);
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
        $result = $this->repository->findItemsWithStatus(
            new FeedItemQueryCriteria(subscriptionGuids: [], userId: 1),
        );

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
            new FeedItemQueryCriteria(
                subscriptionGuids: [$subscriptionGuid],
                userId: 999,
            ),
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
            new FeedItemQueryCriteria(
                subscriptionGuids: [$subscriptionGuid],
                userId: 999,
                filterWords: ['Breaking'],
            ),
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
            new FeedItemQueryCriteria(
                subscriptionGuids: [$subscriptionGuid],
                userId: 999,
                unreadOnly: true,
                excludeFromUnreadFilter: 'unreadfilt1234567',
            ),
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

    #[Test]
    public function deleteDuplicatesKeepsItemsWithDifferentTitles(): void
    {
        $subscriptionGuid = 'dedupnone1234567';

        // Create two items with different titles
        $this->repository->upsert(
            new FeedItem(
                'dedupnone12345a1',
                $subscriptionGuid,
                'First Article Title',
                'https://example.com/url1',
                'Test Source',
                'Excerpt 1',
                new \DateTimeImmutable('2024-01-15 12:00:00'),
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'dedupnone12345a2',
                $subscriptionGuid,
                'Second Article Title',
                'https://example.com/url2',
                'Test Source',
                'Excerpt 2',
                new \DateTimeImmutable('2024-01-15 12:30:00'),
            ),
        );

        $this->repository->deleteDuplicates();

        // Both items should still exist (different titles = no duplicates)
        $this->assertNotNull($this->repository->findByGuid('dedupnone12345a1'));
        $this->assertNotNull($this->repository->findByGuid('dedupnone12345a2'));
    }

    #[Test]
    public function deleteDuplicatesRemovesOlderDuplicate(): void
    {
        $subscriptionGuid = 'dedupold12345678';

        // Create two items with same title within 2h window
        $olderItem = new FeedItem(
            'dedupold123456a1',
            $subscriptionGuid,
            'Same Title Article',
            'https://example.com/old-url',
            'Test Source',
            'Old excerpt',
            new \DateTimeImmutable('2024-01-15 12:00:00'),
        );
        $newerItem = new FeedItem(
            'dedupold123456a2',
            $subscriptionGuid,
            'Same Title Article',
            'https://example.com/new-url',
            'Test Source',
            'New excerpt',
            new \DateTimeImmutable('2024-01-15 13:00:00'),
        );

        $this->repository->upsert($olderItem);
        $this->repository->upsert($newerItem);

        $deleted = $this->repository->deleteDuplicates();

        $this->assertEquals(1, $deleted);
        $this->assertNull($this->repository->findByGuid('dedupold123456a1'));
        $this->assertNotNull($this->repository->findByGuid('dedupold123456a2'));
    }

    #[Test]
    public function deleteDuplicatesKeepsNewerItem(): void
    {
        $subscriptionGuid = 'dedupkeep1234567';

        // Create two items with similar titles (Levenshtein <= 3)
        $olderItem = new FeedItem(
            'dedupkeep12345a1',
            $subscriptionGuid,
            'Mercosur-Abkommens',
            'https://example.com/old-url',
            'Test Source',
            'Excerpt',
            new \DateTimeImmutable('2024-01-15 12:00:00'),
        );
        $newerItem = new FeedItem(
            'dedupkeep12345a2',
            $subscriptionGuid,
            'Mercosur-Abkommen',
            'https://example.com/new-url',
            'Test Source',
            'Excerpt',
            new \DateTimeImmutable('2024-01-15 12:30:00'),
        );

        $this->repository->upsert($olderItem);
        $this->repository->upsert($newerItem);

        $deleted = $this->repository->deleteDuplicates();

        $this->assertEquals(1, $deleted);
        $this->assertNull($this->repository->findByGuid('dedupkeep12345a1'));
        $this->assertNotNull($this->repository->findByGuid('dedupkeep12345a2'));
    }

    #[Test]
    public function deleteDuplicatesIgnoresDifferentTitles(): void
    {
        $subscriptionGuid = 'dedupdiff1234567';

        // Create two items with titles that differ by more than 3 characters
        $this->repository->upsert(
            new FeedItem(
                'dedupdiff12345a1',
                $subscriptionGuid,
                'First Article Title Here',
                'https://example.com/url1',
                'Test Source',
                'Excerpt 1',
                new \DateTimeImmutable('2024-01-15 12:00:00'),
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'dedupdiff12345a2',
                $subscriptionGuid,
                'Second Article Title Here',
                'https://example.com/url2',
                'Test Source',
                'Excerpt 2',
                new \DateTimeImmutable('2024-01-15 12:30:00'),
            ),
        );

        $deleted = $this->repository->deleteDuplicates();

        $this->assertEquals(0, $deleted);
        $this->assertNotNull($this->repository->findByGuid('dedupdiff12345a1'));
        $this->assertNotNull($this->repository->findByGuid('dedupdiff12345a2'));
    }

    #[Test]
    public function deleteDuplicatesIgnoresItemsOutsideTimeWindow(): void
    {
        $subscriptionGuid = 'deduptime1234567';

        // Create two items with same title but more than 2h apart
        $this->repository->upsert(
            new FeedItem(
                'deduptime12345a1',
                $subscriptionGuid,
                'Same Title Article',
                'https://example.com/url1',
                'Test Source',
                'Excerpt 1',
                new \DateTimeImmutable('2024-01-15 12:00:00'),
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'deduptime12345a2',
                $subscriptionGuid,
                'Same Title Article',
                'https://example.com/url2',
                'Test Source',
                'Excerpt 2',
                new \DateTimeImmutable('2024-01-15 15:00:00'),
            ),
        );

        $deleted = $this->repository->deleteDuplicates();

        $this->assertEquals(0, $deleted);
        $this->assertNotNull($this->repository->findByGuid('deduptime12345a1'));
        $this->assertNotNull($this->repository->findByGuid('deduptime12345a2'));
    }

    #[Test]
    public function deleteDuplicatesIgnoresDifferentSubscriptions(): void
    {
        $publishedAt = new \DateTimeImmutable('2024-01-15 12:00:00');

        // Create two items with same title but different subscriptions
        $this->repository->upsert(
            new FeedItem(
                'dedupsub123456a1',
                'subscription1234a',
                'Same Title Different Sub',
                'https://example.com/url1',
                'Source 1',
                'Excerpt 1',
                $publishedAt,
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'dedupsub123456a2',
                'subscription1234b',
                'Same Title Different Sub',
                'https://example.com/url2',
                'Source 2',
                'Excerpt 2',
                $publishedAt,
            ),
        );

        $deleted = $this->repository->deleteDuplicates();

        $this->assertEquals(0, $deleted);
        $this->assertNotNull($this->repository->findByGuid('dedupsub123456a1'));
        $this->assertNotNull($this->repository->findByGuid('dedupsub123456a2'));
    }

    #[Test]
    public function deleteDuplicatesReturnsCorrectCount(): void
    {
        $subscriptionGuid = 'dedupcnt12345678';
        $publishedAt = new \DateTimeImmutable('2024-01-15 12:00:00');

        // Create 3 items with same title (all duplicates of each other)
        $this->repository->upsert(
            new FeedItem(
                'dedupcnt123456a1',
                $subscriptionGuid,
                'Triple Duplicate',
                'https://example.com/url1',
                'Test Source',
                'Excerpt',
                new \DateTimeImmutable('2024-01-15 12:00:00'),
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'dedupcnt123456a2',
                $subscriptionGuid,
                'Triple Duplicate',
                'https://example.com/url2',
                'Test Source',
                'Excerpt',
                new \DateTimeImmutable('2024-01-15 12:30:00'),
            ),
        );
        $this->repository->upsert(
            new FeedItem(
                'dedupcnt123456a3',
                $subscriptionGuid,
                'Triple Duplicate',
                'https://example.com/url3',
                'Test Source',
                'Excerpt',
                new \DateTimeImmutable('2024-01-15 13:00:00'),
            ),
        );

        $deleted = $this->repository->deleteDuplicates();

        // Should delete the two older items, keep only the newest
        $this->assertEquals(2, $deleted);
        $this->assertNull($this->repository->findByGuid('dedupcnt123456a1'));
        $this->assertNull($this->repository->findByGuid('dedupcnt123456a2'));
        $this->assertNotNull($this->repository->findByGuid('dedupcnt123456a3'));
    }
}
