<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Subscription;
use App\Repository\BookmarkStatusRepository;
use App\Repository\FeedItemQueryCriteria;
use App\Repository\FeedItemRepository;
use App\Service\FeedViewService;
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
        $service = $this->createServiceWithItems();

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

        $subscription1 = $this->createStub(Subscription::class);
        $subscription1->method('getGuid')->willReturn('sguid1');
        $subscription1->method('getName')->willReturn('Feed 1');

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionsForUser')
            ->willReturn([$subscription1]);
        $subscriptionService
            ->method('getSubscriptionsWithUnreadCounts')
            ->willReturn([]);

        // Repository should receive sguid filter
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->method('getUnreadCountsBySubscription')
            ->willReturn([]);
        $feedItemRepository
            ->expects($this->once())
            ->method('findItemsWithStatus')
            ->with(
                $this->callback(function (FeedItemQueryCriteria $criteria) use (
                    $userId,
                ) {
                    return $criteria->subscriptionGuids === ['sguid1']
                        && $criteria->userId === $userId
                        && $criteria->filterWords === []
                        && $criteria->unreadOnly === false
                        && $criteria->limit === 50
                        && $criteria->subscriptionGuid === 'sguid1'
                        && $criteria->excludeFromUnreadFilter === null;
                }),
            )
            ->willReturn([
                [
                    'guid' => 'guid1',
                    'sguid' => 'sguid1',
                    'isRead' => false,
                    'isNew' => true,
                ],
                [
                    'guid' => 'guid2',
                    'sguid' => 'sguid1',
                    'isRead' => false,
                    'isNew' => true,
                ],
            ]);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getViewData($userId, 'sguid1');

        $this->assertCount(2, $result['items']);
    }

    #[Test]
    public function getViewDataFiltersUnreadOnly(): void
    {
        $userId = 1;

        $subscription1 = $this->createStub(Subscription::class);
        $subscription1->method('getGuid')->willReturn('sguid1');
        $subscription1->method('getName')->willReturn('Feed 1');

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionsForUser')
            ->willReturn([$subscription1]);
        $subscriptionService
            ->method('getSubscriptionsWithUnreadCounts')
            ->willReturn([]);

        // Repository should receive unreadOnly=true
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->method('getUnreadCountsBySubscription')
            ->willReturn([]);
        $feedItemRepository
            ->expects($this->once())
            ->method('findItemsWithStatus')
            ->with(
                $this->callback(function (FeedItemQueryCriteria $criteria) use (
                    $userId,
                ) {
                    return $criteria->subscriptionGuids === ['sguid1']
                        && $criteria->userId === $userId
                        && $criteria->filterWords === []
                        && $criteria->unreadOnly === true
                        && $criteria->limit === 50
                        && $criteria->subscriptionGuid === null
                        && $criteria->excludeFromUnreadFilter === null;
                }),
            )
            ->willReturn([
                [
                    'guid' => 'guid1',
                    'sguid' => 'sguid1',
                    'isRead' => false,
                    'isNew' => true,
                ],
            ]);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getViewData($userId, null, null, true);

        foreach ($result['items'] as $item) {
            $this->assertFalse($item['isRead']);
        }
    }

    #[Test]
    public function getViewDataAppliesLimit(): void
    {
        $userId = 1;

        $subscription1 = $this->createStub(Subscription::class);
        $subscription1->method('getGuid')->willReturn('sguid1');
        $subscription1->method('getName')->willReturn('Feed 1');

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionsForUser')
            ->willReturn([$subscription1]);
        $subscriptionService
            ->method('getSubscriptionsWithUnreadCounts')
            ->willReturn([]);

        // Repository should receive limit=2
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->method('getUnreadCountsBySubscription')
            ->willReturn([]);
        $feedItemRepository
            ->expects($this->once())
            ->method('findItemsWithStatus')
            ->with(
                $this->callback(function (FeedItemQueryCriteria $criteria) use (
                    $userId,
                ) {
                    return $criteria->subscriptionGuids === ['sguid1']
                        && $criteria->userId === $userId
                        && $criteria->filterWords === []
                        && $criteria->unreadOnly === false
                        && $criteria->limit === 2
                        && $criteria->subscriptionGuid === null
                        && $criteria->excludeFromUnreadFilter === null;
                }),
            )
            ->willReturn([
                [
                    'guid' => 'guid1',
                    'sguid' => 'sguid1',
                    'isRead' => false,
                    'isNew' => true,
                ],
                [
                    'guid' => 'guid2',
                    'sguid' => 'sguid1',
                    'isRead' => false,
                    'isNew' => true,
                ],
            ]);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getViewData($userId, null, null, false, 2);

        $this->assertCount(2, $result['items']);
    }

    #[Test]
    public function getViewDataFindsActiveItem(): void
    {
        $userId = 1;
        $service = $this->createServiceWithItems();

        $result = $service->getViewData($userId, null, 'guid2');

        $this->assertNotNull($result['activeItem']);
        $this->assertEquals('guid2', $result['activeItem']['guid']);
    }

    #[Test]
    public function getViewDataReturnsNullForUnknownActiveItem(): void
    {
        $userId = 1;
        $service = $this->createServiceWithItems();

        $result = $service->getViewData($userId, null, 'unknown-guid');

        $this->assertNull($result['activeItem']);
    }

    #[Test]
    public function getAllItemGuidsReturnsGuids(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionGuids')
            ->willReturn(['sguid1']);

        $feedItemRepository = $this->createStub(FeedItemRepository::class);
        $feedItemRepository->method('findItemsWithStatus')->willReturn([
            [
                'guid' => 'guid1',
                'sguid' => 'sguid1',
                'isRead' => false,
                'isNew' => true,
            ],
            [
                'guid' => 'guid2',
                'sguid' => 'sguid1',
                'isRead' => false,
                'isNew' => true,
            ],
        ]);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getAllItemGuids($userId);

        $this->assertEquals(['guid1', 'guid2'], $result);
    }

    #[Test]
    public function getItemGuidsForSubscriptionUsesRepository(): void
    {
        $userId = 1;

        $subscriptionService = $this->createStub(SubscriptionService::class);

        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->expects($this->once())
            ->method('getItemGuidsBySubscription')
            ->with('sguid1')
            ->willReturn(['guid1', 'guid3']);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getItemGuidsForSubscription($userId, 'sguid1');

        $this->assertEquals(['guid1', 'guid3'], $result);
    }

    private function createServiceWithItems(): FeedViewService
    {
        $subscription1 = $this->createStub(Subscription::class);
        $subscription1->method('getGuid')->willReturn('sguid1');
        $subscription1->method('getName')->willReturn('Feed 1');

        $subscription2 = $this->createStub(Subscription::class);
        $subscription2->method('getGuid')->willReturn('sguid2');
        $subscription2->method('getName')->willReturn('Feed 2');

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionsForUser')
            ->willReturn([$subscription1, $subscription2]);
        $subscriptionService
            ->method('getSubscriptionGuids')
            ->willReturn(['sguid1', 'sguid2']);
        $subscriptionService
            ->method('getSubscriptionsWithUnreadCounts')
            ->willReturn([]);

        $feedItemRepository = $this->createStub(FeedItemRepository::class);
        $feedItemRepository
            ->method('getUnreadCountsBySubscription')
            ->willReturn(['sguid1' => 1, 'sguid2' => 1]);
        $feedItemRepository->method('findItemsWithStatus')->willReturn([
            [
                'guid' => 'guid1',
                'sguid' => 'sguid1',
                'isRead' => true,
                'isNew' => false,
            ],
            [
                'guid' => 'guid2',
                'sguid' => 'sguid1',
                'isRead' => false,
                'isNew' => true,
            ],
            [
                'guid' => 'guid3',
                'sguid' => 'sguid2',
                'isRead' => true,
                'isNew' => false,
            ],
            [
                'guid' => 'guid4',
                'sguid' => 'sguid2',
                'isRead' => false,
                'isNew' => true,
            ],
        ]);

        return new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );
    }

    private function createUserPreferenceServiceStub(): UserPreferenceService
    {
        $stub = $this->createStub(UserPreferenceService::class);
        $stub->method('getFilterWords')->willReturn([]);

        return $stub;
    }

    private function createBookmarkStatusRepositoryStub(): BookmarkStatusRepository
    {
        $stub = $this->createStub(BookmarkStatusRepository::class);
        $stub->method('countByUser')->willReturn(0);

        return $stub;
    }

    #[Test]
    public function getViewDataFiltersItemsByWordViaRepository(): void
    {
        $userId = 1;

        $subscription1 = $this->createStub(Subscription::class);
        $subscription1->method('getGuid')->willReturn('sguid1');
        $subscription1->method('getName')->willReturn('Feed 1');

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionsForUser')
            ->willReturn([$subscription1]);
        $subscriptionService
            ->method('getSubscriptionGuids')
            ->willReturn(['sguid1']);
        $subscriptionService
            ->method('getSubscriptionsWithUnreadCounts')
            ->willReturn([]);

        // The repository is expected to receive the filter words
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->method('getUnreadCountsBySubscription')
            ->willReturn([]);
        $feedItemRepository
            ->expects($this->once())
            ->method('findItemsWithStatus')
            ->with(
                $this->callback(function (FeedItemQueryCriteria $criteria) use (
                    $userId,
                ) {
                    return $criteria->subscriptionGuids === ['sguid1']
                        && $criteria->userId === $userId
                        && $criteria->filterWords === ['sponsored']
                        && $criteria->unreadOnly === false
                        && $criteria->limit === 50
                        && $criteria->subscriptionGuid === null
                        && $criteria->excludeFromUnreadFilter === null;
                }),
            )
            ->willReturn([
                [
                    'guid' => 'guid1',
                    'sguid' => 'sguid1',
                    'title' => 'Good article',
                    'excerpt' => 'Content here',
                    'isRead' => false,
                    'isNew' => true,
                ],
                [
                    'guid' => 'guid3',
                    'sguid' => 'sguid1',
                    'title' => 'Another good one',
                    'excerpt' => 'More content',
                    'isRead' => false,
                    'isNew' => true,
                ],
            ]);

        $userPreferenceService = $this->createStub(
            UserPreferenceService::class,
        );
        $userPreferenceService
            ->method('getFilterWords')
            ->willReturn(['sponsored']);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $userPreferenceService,
        );

        $result = $service->getViewData($userId);

        $this->assertCount(2, $result['items']);
        $this->assertEquals('guid1', $result['items'][0]['guid']);
        $this->assertEquals('guid3', $result['items'][1]['guid']);
    }

    #[Test]
    public function getViewDataGroupsFeedsByFolder(): void
    {
        $userId = 1;

        $subscription1 = $this->createStub(Subscription::class);
        $subscription1->method('getGuid')->willReturn('sguid1');
        $subscription1->method('getName')->willReturn('Feed 1');

        $subscription2 = $this->createStub(Subscription::class);
        $subscription2->method('getGuid')->willReturn('sguid2');
        $subscription2->method('getName')->willReturn('Feed 2');

        $subscriptionService = $this->createStub(SubscriptionService::class);
        $subscriptionService
            ->method('getSubscriptionsForUser')
            ->willReturn([$subscription1, $subscription2]);
        $subscriptionService
            ->method('getSubscriptionsWithUnreadCounts')
            ->willReturn([
                ['guid' => 'sguid1', 'name' => 'Feed 1', 'folder' => 'Tech'],
                ['guid' => 'sguid2', 'name' => 'Feed 2', 'folder' => null],
            ]);

        $feedItemRepository = $this->createStub(FeedItemRepository::class);
        $feedItemRepository
            ->method('getUnreadCountsBySubscription')
            ->willReturn([]);
        $feedItemRepository->method('findItemsWithStatus')->willReturn([]);

        $service = new FeedViewService(
            $feedItemRepository,
            $this->createBookmarkStatusRepositoryStub(),
            $subscriptionService,
            $this->createUserPreferenceServiceStub(),
        );

        $result = $service->getViewData($userId);

        $this->assertArrayHasKey('groupedFeeds', $result);
        $this->assertArrayHasKey('ungroupedFeeds', $result);
        $this->assertArrayHasKey('Tech', $result['groupedFeeds']);
        $this->assertCount(1, $result['groupedFeeds']['Tech']);
        $this->assertCount(1, $result['ungroupedFeeds']);
        $this->assertEquals('sguid2', $result['ungroupedFeeds'][0]['guid']);
    }
}
