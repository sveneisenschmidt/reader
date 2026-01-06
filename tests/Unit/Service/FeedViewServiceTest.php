<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Service\FeedFetcher;
use App\Service\FeedViewService;
use App\Service\ReadStatusService;
use App\Service\SeenStatusService;
use App\Service\SubscriptionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FeedViewServiceTest extends TestCase
{
    private FeedFetcher&MockObject $feedFetcher;
    private SubscriptionService&MockObject $subscriptionService;
    private ReadStatusService&MockObject $readStatusService;
    private SeenStatusService&MockObject $seenStatusService;
    private FeedViewService $service;

    protected function setUp(): void
    {
        $this->feedFetcher = $this->createMock(FeedFetcher::class);
        $this->subscriptionService = $this->createMock(SubscriptionService::class);
        $this->readStatusService = $this->createMock(ReadStatusService::class);
        $this->seenStatusService = $this->createMock(SeenStatusService::class);

        $this->service = new FeedViewService(
            $this->feedFetcher,
            $this->subscriptionService,
            $this->readStatusService,
            $this->seenStatusService,
        );
    }

    #[Test]
    public function getViewDataReturnsStructuredData(): void
    {
        $userId = 1;
        $this->setupMocksForEnrichedItems($userId);

        $result = $this->service->getViewData($userId);

        $this->assertArrayHasKey('feeds', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('allItemsCount', $result);
        $this->assertArrayHasKey('activeItem', $result);
    }

    #[Test]
    public function getViewDataFiltersItemsBySubscription(): void
    {
        $userId = 1;
        $this->setupMocksForEnrichedItems($userId);

        $result = $this->service->getViewData($userId, 'sguid1');

        $this->assertCount(2, $result['items']);
        foreach ($result['items'] as $item) {
            $this->assertEquals('sguid1', $item['sguid']);
        }
    }

    #[Test]
    public function getViewDataFiltersUnreadOnly(): void
    {
        $userId = 1;
        $this->setupMocksForEnrichedItems($userId);

        $result = $this->service->getViewData($userId, null, null, true);

        foreach ($result['items'] as $item) {
            $this->assertFalse($item['isRead']);
        }
    }

    #[Test]
    public function getViewDataAppliesLimit(): void
    {
        $userId = 1;
        $this->setupMocksForEnrichedItems($userId);

        $result = $this->service->getViewData($userId, null, null, false, 2);

        $this->assertCount(2, $result['items']);
    }

    #[Test]
    public function getViewDataFindsActiveItem(): void
    {
        $userId = 1;
        $this->setupMocksForEnrichedItems($userId);

        $result = $this->service->getViewData($userId, null, 'guid2');

        $this->assertNotNull($result['activeItem']);
        $this->assertEquals('guid2', $result['activeItem']['guid']);
    }

    #[Test]
    public function getViewDataReturnsNullForUnknownActiveItem(): void
    {
        $userId = 1;
        $this->setupMocksForEnrichedItems($userId);

        $result = $this->service->getViewData($userId, null, 'unknown-guid');

        $this->assertNull($result['activeItem']);
    }

    #[Test]
    public function getAllItemGuidsReturnsGuids(): void
    {
        $userId = 1;
        $this->subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);
        $this->feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1'],
            ['guid' => 'guid2'],
        ]);

        $result = $this->service->getAllItemGuids($userId);

        $this->assertEquals(['guid1', 'guid2'], $result);
    }

    #[Test]
    public function getItemGuidsForSubscriptionFiltersCorrectly(): void
    {
        $userId = 1;
        $this->subscriptionService->method('getFeedGuids')->willReturn(['sguid1', 'sguid2']);
        $this->feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'],
            ['guid' => 'guid2', 'sguid' => 'sguid2'],
            ['guid' => 'guid3', 'sguid' => 'sguid1'],
        ]);

        $result = $this->service->getItemGuidsForSubscription($userId, 'sguid1');

        $this->assertEquals(['guid1', 'guid3'], $result);
    }

    #[Test]
    public function findNextItemGuidReturnsNextItem(): void
    {
        $userId = 1;
        $this->subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);
        $this->feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'],
            ['guid' => 'guid2', 'sguid' => 'sguid1'],
            ['guid' => 'guid3', 'sguid' => 'sguid1'],
        ]);

        $result = $this->service->findNextItemGuid($userId, null, 'guid1');

        $this->assertEquals('guid2', $result);
    }

    #[Test]
    public function findNextItemGuidReturnsNullForLastItem(): void
    {
        $userId = 1;
        $this->subscriptionService->method('getFeedGuids')->willReturn(['sguid1']);
        $this->feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'],
            ['guid' => 'guid2', 'sguid' => 'sguid1'],
        ]);

        $result = $this->service->findNextItemGuid($userId, null, 'guid2');

        $this->assertNull($result);
    }

    #[Test]
    public function findNextItemGuidFiltersbySubscription(): void
    {
        $userId = 1;
        $this->subscriptionService->method('getFeedGuids')->willReturn(['sguid1', 'sguid2']);
        $this->feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'],
            ['guid' => 'guid2', 'sguid' => 'sguid2'],
            ['guid' => 'guid3', 'sguid' => 'sguid1'],
        ]);

        $result = $this->service->findNextItemGuid($userId, 'sguid1', 'guid1');

        $this->assertEquals('guid3', $result);
    }

    private function setupMocksForEnrichedItems(int $userId): void
    {
        $this->subscriptionService->method('getFeedGuids')->willReturn(['sguid1', 'sguid2']);

        $this->feedFetcher->method('getAllItems')->willReturn([
            ['guid' => 'guid1', 'sguid' => 'sguid1'],
            ['guid' => 'guid2', 'sguid' => 'sguid1'],
            ['guid' => 'guid3', 'sguid' => 'sguid2'],
            ['guid' => 'guid4', 'sguid' => 'sguid2'],
        ]);

        $this->subscriptionService->method('enrichItemsWithSubscriptionNames')
            ->willReturnCallback(fn($items) => $items);

        $this->readStatusService->method('enrichItemsWithReadStatus')
            ->willReturnCallback(fn($items) => array_map(
                fn($item, $i) => array_merge($item, ['isRead' => $i % 2 === 0]),
                $items,
                array_keys($items)
            ));

        $this->seenStatusService->method('enrichItemsWithSeenStatus')
            ->willReturnCallback(fn($items) => array_map(
                fn($item) => array_merge($item, ['isNew' => false]),
                $items
            ));

        $this->subscriptionService->method('getSubscriptionsWithCounts')
            ->willReturn([]);
    }
}
