<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Service\FeedPersistenceService;
use App\Service\FeedViewService;
use App\Service\ReadStatusService;
use App\Service\SeenStatusService;
use App\Service\SubscriptionService;
use App\Service\UserPreferenceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedViewServiceTest extends TestCase
{
    #[Test]
    public function getViewDataReturnsStructuredData(): void
    {
        $userId = 1;
        $service = $this->createServiceWithEnrichedItems();

        $result = $service->getViewData($userId);

        $this->assertArrayHasKey('feeds', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('allItemsCount', $result);
        $this->assertArrayHasKey('activeItem', $result);
    }

    #[Test]
    public function getViewDataFiltersItemsBySubscription(): void
    {
        $userId = 1;
        $service = $this->createServiceWithEnrichedItems();

        $result = $service->getViewData($userId, 'sguid1');

        $this->assertCount(2, $result['items']);
        foreach ($result['items'] as $item) {
            $this->assertEquals('sguid1', $item['sguid']);
        }
    }

    #[Test]
    public function getViewDataFiltersUnreadOnly(): void
    {
        $userId = 1;
        $service = $this->createServiceWithEnrichedItems();

        $result = $service->getViewData($userId, null, null, true);

        foreach ($result['items'] as $item) {
            $this->assertFalse($item['isRead']);
        }
    }

    #[Test]
    public function getViewDataAppliesLimit(): void
    {
        $userId = 1;
        $service = $this->createServiceWithEnrichedItems();

        $result = $service->getViewData($userId, null, null, false, 2);

        $this->assertCount(2, $result['items']);
    }

    #[Test]
    public function getViewDataFindsActiveItem(): void
    {
        $userId = 1;
        $service = $this->createServiceWithEnrichedItems();

        $result = $service->getViewData($userId, null, 'guid2');

        $this->assertNotNull($result['activeItem']);
        $this->assertEquals('guid2', $result['activeItem']['guid']);
    }

    #[Test]
    public function getViewDataReturnsNullForUnknownActiveItem(): void
    {
        $userId = 1;
        $service = $this->createServiceWithEnrichedItems();

        $result = $service->getViewData($userId, null, 'unknown-guid');

        $this->assertNull($result['activeItem']);
    }

    #[Test]
    public function getAllItemGuidsReturnsGuids(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([['guid' => 'guid1'], ['guid' => 'guid2']]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getAllItemGuids($userId);

        $this->assertEquals(['guid1', 'guid2'], $result);
    }

    #[Test]
    public function getItemGuidsForSubscriptionFiltersCorrectly(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid2'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getItemGuidsForSubscription($userId, 'sguid1');

        $this->assertEquals(['guid1', 'guid3'], $result);
    }

    #[Test]
    public function findNextItemGuidReturnsNextItem(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->findNextItemGuid($userId, null, 'guid1');

        $this->assertEquals('guid2', $result);
    }

    #[Test]
    public function findNextItemGuidReturnsNullForLastItem(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
            ]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->findNextItemGuid($userId, null, 'guid2');

        $this->assertNull($result);
    }

    #[Test]
    public function findNextItemGuidFiltersbySubscription(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid2'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->findNextItemGuid($userId, 'sguid1', 'guid1');

        $this->assertEquals('guid3', $result);
    }

    #[Test]
    public function findNextUnreadItemGuidReturnsNextUnreadItem(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, [
                        'isRead' => $item['guid'] === 'guid2',
                    ]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->findNextUnreadItemGuid($userId, null, 'guid1');

        $this->assertEquals('guid3', $result);
    }

    #[Test]
    public function findNextUnreadItemGuidReturnsNullWhenNoUnreadItems(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
            ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, ['isRead' => true]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->findNextUnreadItemGuid($userId, null, 'guid1');

        $this->assertNull($result);
    }

    #[Test]
    public function findNextUnreadItemGuidFiltersbySubscription(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid2'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, ['isRead' => false]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->findNextUnreadItemGuid($userId, 'sguid1', 'guid1');

        $this->assertEquals('guid3', $result);
    }

    #[Test]
    public function findNextItemGuidRespectsLimit(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // With limit of 2, guid3 is outside the limit
        $result = $service->findNextItemGuid($userId, null, 'guid2', 2);

        $this->assertNull($result);
    }

    #[Test]
    public function findNextUnreadItemGuidRespectsLimit(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, ['isRead' => false]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // With limit of 2, guid3 is outside the limit even though it's unread
        $result = $service->findNextUnreadItemGuid($userId, null, 'guid2', 2);

        $this->assertNull($result);
    }

    /**
     * Test for Issue #40: In "All" view with "unread only" filter active,
     * findNextUnreadItemGuid should find unread items across subscriptions
     * when the current item's subscription has no more unread items.
     */
    #[Test]
    public function findNextUnreadItemGuidWithUnreadOnlyFilterFindsItemsAcrossSubscriptions(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        // Items: 3 read from sguid1, then 3 unread from sguid2
        // When viewing with unread only filter, user only sees the 3 unread items
        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid2', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid3', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid4', 'sguid' => 'sguid2'], // unread - this is visible with filter
            ['guid' => 'guid5', 'sguid' => 'sguid2'], // unread
            ['guid' => 'guid6', 'sguid' => 'sguid2'], // unread
        ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, [
                        'isRead' => in_array($item['guid'], [
                            'guid1',
                            'guid2',
                            'guid3',
                        ]),
                    ]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // User is viewing guid4 with unread only filter (limit 3 shows guid4, guid5, guid6)
        // After marking guid4 as read, should find guid5 as next unread
        // With unreadOnly=true, limit applies to unread items only
        $result = $service->findNextUnreadItemGuid(
            $userId,
            null, // All view (no specific subscription)
            'guid4',
            3, // limit of 3
            true, // unreadOnly filter is active
        );

        $this->assertEquals('guid5', $result);
    }

    /**
     * Test for Issue #40: Verifies the bug scenario where limit is applied
     * to all items (including read) instead of just unread items.
     */
    #[Test]
    public function findNextUnreadItemGuidWithUnreadOnlyFilterAndLimitAppliedToUnreadItems(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        // Scenario: 10 items total, first 8 are read, last 2 are unread
        // With limit=3 and unreadOnly=false: limit applied to all items, shows guid1,guid2,guid3
        // With limit=3 and unreadOnly=true: limit should apply to unread items, shows guid9,guid10
        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid2', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid3', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid4', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid5', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid6', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid7', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid8', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid9', 'sguid' => 'sguid2'], // unread
            ['guid' => 'guid10', 'sguid' => 'sguid2'], // unread
        ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, [
                        'isRead' => !in_array($item['guid'], [
                            'guid9',
                            'guid10',
                        ]),
                    ]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // User is viewing guid9 with unread only filter active
        // The view shows only guid9, guid10 (the 2 unread items within limit)
        // After marking guid9 as read, should find guid10 as next
        $result = $service->findNextUnreadItemGuid(
            $userId,
            null, // All view
            'guid9',
            3, // limit - but should apply to filtered list
            true, // unreadOnly filter is active
        );

        // This should return guid10 because with unreadOnly=true,
        // the limit should be applied after filtering to unread items
        $this->assertEquals('guid10', $result);
    }

    /**
     * Test for Issue #40: Without unreadOnly filter, behavior should be unchanged.
     */
    #[Test]
    public function findNextUnreadItemGuidWithoutUnreadOnlyFilterBehavesAsBeforeIssue40(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid2', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid3', 'sguid' => 'sguid1'], // unread
        ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, [
                        'isRead' => $item['guid'] !== 'guid3',
                    ]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // Without unreadOnly filter (false), limit applies to all items
        // With limit=2, only guid1 and guid2 are considered
        // guid3 is outside the limit, so should return null
        $result = $service->findNextUnreadItemGuid(
            $userId,
            null,
            'guid2',
            2,
            false, // unreadOnly filter is NOT active
        );

        $this->assertNull($result);
    }

    /**
     * Test for Issue #40: findNextItemGuid with unreadOnly filter should
     * find the next item in the filtered (unread only) list.
     */
    #[Test]
    public function findNextItemGuidWithUnreadOnlyFilterFindsNextInFilteredList(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);

        // Items: read, read, unread, unread
        // With unreadOnly filter, user sees: guid3, guid4
        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid2', 'sguid' => 'sguid1'], // read
            ['guid' => 'guid3', 'sguid' => 'sguid2'], // unread
            ['guid' => 'guid4', 'sguid' => 'sguid2'], // unread
        ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, [
                        'isRead' => in_array($item['guid'], ['guid1', 'guid2']),
                    ]),
                    $items,
                ),
            );

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // With unreadOnly=true, findNextItemGuid should find guid4 after guid3
        $result = $service->findNextItemGuid(
            $userId,
            null,
            'guid3',
            10,
            true, // unreadOnly filter is active
        );

        $this->assertEquals('guid4', $result);
    }

    /**
     * Test for Issue #40: findNextItemGuid without unreadOnly filter
     * should behave as before (find next item in all items list).
     */
    #[Test]
    public function findNextItemGuidWithoutUnreadOnlyFilterBehavesAsBeforeIssue40(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
                ['guid' => 'guid3', 'sguid' => 'sguid1'],
            ]);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $this->createStub(ReadStatusService::class),
            $this->createStub(SeenStatusService::class),
            $this->createUserPreferenceServiceStub(),
        );

        // Without unreadOnly filter, should find guid2 after guid1
        $result = $service->findNextItemGuid(
            $userId,
            null,
            'guid1',
            10,
            false, // unreadOnly filter is NOT active
        );

        $this->assertEquals('guid2', $result);
    }

    private function createServiceWithEnrichedItems(): FeedViewService
    {
        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getFeedGuids')
            ->willReturn(['sguid1', 'sguid2']);
        $subscriptionService
            ->method('enrichItemsWithSubscriptionNames')
            ->willReturnCallback(fn ($items) => $items);
        $subscriptionService
            ->method('getSubscriptionsWithCounts')
            ->willReturn([]);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher
            ->method('getAllItems')
            ->willReturn([
                ['guid' => 'guid1', 'sguid' => 'sguid1'],
                ['guid' => 'guid2', 'sguid' => 'sguid1'],
                ['guid' => 'guid3', 'sguid' => 'sguid2'],
                ['guid' => 'guid4', 'sguid' => 'sguid2'],
            ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item, $i) => array_merge($item, [
                        'isRead' => $i % 2 === 0,
                    ]),
                    $items,
                    array_keys($items),
                ),
            );

        $seenStatusService = $this->createStub(SeenStatusService::class);
        $seenStatusService
            ->method('enrichItemsWithSeenStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, ['isNew' => false]),
                    $items,
                ),
            );

        return new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $seenStatusService,
            $this->createUserPreferenceServiceStub(),
        );
    }

    private function createUserPreferenceServiceStub(): UserPreferenceService
    {
        $stub = $this->createStub(UserPreferenceService::class);
        $stub->method('getFilterWords')->willReturn([]);

        return $stub;
    }

    #[Test]
    public function getViewDataFiltersItemsByWord(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);
        $subscriptionService->method('getSubscriptionsForUser')->willReturn([]);
        $subscriptionService
            ->method('getSubscriptionsWithCounts')
            ->willReturn([]);

        $feedFetcher = $this->createStub(FeedPersistenceService::class);
        $feedFetcher->method('getAllItems')->willReturn([
            [
                'guid' => 'guid1',
                'sguid' => 'sguid1',
                'title' => 'Good article',
                'excerpt' => 'Content here',
            ],
            [
                'guid' => 'guid2',
                'sguid' => 'sguid1',
                'title' => 'Sponsored post',
                'excerpt' => 'Buy now',
            ],
            [
                'guid' => 'guid3',
                'sguid' => 'sguid1',
                'title' => 'Another good one',
                'excerpt' => 'More content',
            ],
        ]);

        $readStatusService = $this->createStub(ReadStatusService::class);
        $readStatusService
            ->method('enrichItemsWithReadStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, ['isRead' => false]),
                    $items,
                ),
            );

        $seenStatusService = $this->createStub(SeenStatusService::class);
        $seenStatusService
            ->method('enrichItemsWithSeenStatus')
            ->willReturnCallback(
                fn ($items) => array_map(
                    fn ($item) => array_merge($item, ['isNew' => true]),
                    $items,
                ),
            );

        $userPreferenceService = $this->createStub(
            UserPreferenceService::class,
        );
        $userPreferenceService
            ->method('getFilterWords')
            ->willReturn(['sponsored']);

        $service = new FeedViewService(
            $feedFetcher,
            $subscriptionService,
            $readStatusService,
            $seenStatusService,
            $userPreferenceService,
        );

        $result = $service->getViewData($userId);

        $this->assertCount(2, $result['items']);
        $this->assertEquals('guid1', $result['items'][0]['guid']);
        $this->assertEquals('guid3', $result['items'][1]['guid']);
    }
}
