<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Subscriptions\Subscription;
use App\Enum\SubscriptionStatus;
use App\Repository\Content\FeedItemRepository;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Repository\Users\ReadStatusRepository;
use App\Repository\Users\SeenStatusRepository;
use App\Service\FeedContentService;
use App\Service\FeedReaderService;
use App\Service\SubscriptionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SubscriptionServiceTest extends TestCase
{
    #[Test]
    public function getSubscriptionsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                'guid1',
                'Feed 1',
                'https://example.com/feed1',
            ),
            $this->createSubscriptionStub(
                'guid2',
                'Feed 2',
                'https://example.com/feed2',
            ),
        ];

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('findByUserId')
            ->with($userId)
            ->willReturn($subscriptions);

        $service = $this->createService(subscriptionRepository: $repository);

        $result = $service->getSubscriptionsForUser($userId);

        $this->assertSame($subscriptions, $result);
    }

    #[Test]
    public function getSubscriptionsWithCountsCalculatesUnreadCounts(): void
    {
        $userId = 1;
        $sub1 = $this->createSubscriptionStub(
            'guid1',
            'Feed 1',
            'https://example.com/feed1',
        );
        $sub2 = $this->createSubscriptionStub(
            'guid2',
            'Feed 2',
            'https://example.com/feed2',
        );

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$sub1, $sub2]);

        $service = $this->createService(subscriptionRepository: $repository);

        $items = [
            ['sguid' => 'guid1', 'isRead' => false],
            ['sguid' => 'guid1', 'isRead' => false],
            ['sguid' => 'guid1', 'isRead' => true],
            ['sguid' => 'guid2', 'isRead' => false],
        ];

        $result = $service->getSubscriptionsWithCounts($userId, $items);

        $this->assertCount(2, $result);
        $this->assertEquals(2, $result[0]['count']); // guid1 has 2 unread
        $this->assertEquals(1, $result[1]['count']); // guid2 has 1 unread
    }

    #[Test]
    public function getSubscriptionsWithCountsIncludesFolder(): void
    {
        $userId = 1;
        $sub1 = $this->createSubscriptionStub(
            'guid1',
            'Feed 1',
            'https://example.com/feed1',
            'News/Tech',
        );
        $sub2 = $this->createSubscriptionStub(
            'guid2',
            'Feed 2',
            'https://example.com/feed2',
            null,
        );

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$sub1, $sub2]);

        $service = $this->createService(subscriptionRepository: $repository);

        $result = $service->getSubscriptionsWithCounts($userId, []);

        $this->assertEquals('News/Tech', $result[0]['folder']);
        $this->assertNull($result[1]['folder']);
    }

    #[Test]
    public function getFeedUrlsReturnsMappedUrls(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                'guid1',
                'Feed 1',
                'https://example.com/feed1',
            ),
            $this->createSubscriptionStub(
                'guid2',
                'Feed 2',
                'https://example.com/feed2',
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn($subscriptions);

        $service = $this->createService(subscriptionRepository: $repository);

        $result = $service->getFeedUrls($userId);

        $this->assertEquals(
            ['https://example.com/feed1', 'https://example.com/feed2'],
            $result,
        );
    }

    #[Test]
    public function getFeedGuidsReturnsMappedGuids(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                'guid1',
                'Feed 1',
                'https://example.com/feed1',
            ),
            $this->createSubscriptionStub(
                'guid2',
                'Feed 2',
                'https://example.com/feed2',
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn($subscriptions);

        $service = $this->createService(subscriptionRepository: $repository);

        $result = $service->getFeedGuids($userId);

        $this->assertEquals(['guid1', 'guid2'], $result);
    }

    #[Test]
    public function addSubscriptionCreatesNewSubscription(): void
    {
        $userId = 1;
        $url = 'https://example.com/feed';
        $title = 'Example Feed';
        $guid = 'generated-guid';

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->expects($this->once())
            ->method('getFeedTitle')
            ->with($url)
            ->willReturn($title);

        $feedContentService = $this->createMock(FeedContentService::class);
        $feedContentService
            ->expects($this->once())
            ->method('createGuid')
            ->with($url)
            ->willReturn($guid);

        $subscription = $this->createSubscriptionStub($guid, $title, $url);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('addSubscription')
            ->with($userId, $url, $title, $guid)
            ->willReturn($subscription);

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
            feedContentService: $feedContentService,
        );
        $result = $service->addSubscription($userId, $url);

        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function removeSubscriptionDeletesAllRelatedData(): void
    {
        $userId = 1;
        $guid = 'test-guid';
        $feedItemGuids = ['item1', 'item2'];

        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->expects($this->once())
            ->method('getGuidsByFeedGuid')
            ->with($guid)
            ->willReturn($feedItemGuids);
        $feedItemRepository
            ->expects($this->once())
            ->method('deleteByFeedGuid')
            ->with($guid);

        $readStatusRepository = $this->createMock(ReadStatusRepository::class);
        $readStatusRepository
            ->expects($this->once())
            ->method('deleteByFeedItemGuids')
            ->with($userId, $feedItemGuids);

        $seenStatusRepository = $this->createMock(SeenStatusRepository::class);
        $seenStatusRepository
            ->expects($this->once())
            ->method('deleteByFeedItemGuids')
            ->with($userId, $feedItemGuids);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('removeSubscription')
            ->with($userId, $guid);

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedItemRepository: $feedItemRepository,
            readStatusRepository: $readStatusRepository,
            seenStatusRepository: $seenStatusRepository,
        );

        $service->removeSubscription($userId, $guid);
    }

    #[Test]
    public function removeSubscriptionWithNoItemsSkipsStatusDeletion(): void
    {
        $userId = 1;
        $guid = 'test-guid';

        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->expects($this->once())
            ->method('getGuidsByFeedGuid')
            ->with($guid)
            ->willReturn([]);
        $feedItemRepository
            ->expects($this->once())
            ->method('deleteByFeedGuid')
            ->with($guid);

        $readStatusRepository = $this->createMock(ReadStatusRepository::class);
        $readStatusRepository
            ->expects($this->never())
            ->method('deleteByFeedItemGuids');

        $seenStatusRepository = $this->createMock(SeenStatusRepository::class);
        $seenStatusRepository
            ->expects($this->never())
            ->method('deleteByFeedItemGuids');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('removeSubscription')
            ->with($userId, $guid);

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedItemRepository: $feedItemRepository,
            readStatusRepository: $readStatusRepository,
            seenStatusRepository: $seenStatusRepository,
        );

        $service->removeSubscription($userId, $guid);
    }

    #[Test]
    public function updateSubscriptionNameCallsRepository(): void
    {
        $userId = 1;
        $guid = 'test-guid';
        $name = 'New Feed Name';

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('updateName')
            ->with($userId, $guid, $name);

        $service = $this->createService(subscriptionRepository: $repository);

        $service->updateSubscriptionName($userId, $guid, $name);
    }

    #[Test]
    public function enrichItemsWithSubscriptionNamesAddsSourceField(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                'guid1',
                'Feed One',
                'https://example.com/1',
            ),
            $this->createSubscriptionStub(
                'guid2',
                'Feed Two',
                'https://example.com/2',
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn($subscriptions);

        $service = $this->createService(subscriptionRepository: $repository);

        $items = [
            ['sguid' => 'guid1', 'title' => 'Item 1'],
            ['sguid' => 'guid2', 'title' => 'Item 2'],
            ['sguid' => 'unknown', 'title' => 'Item 3'],
        ];

        $result = $service->enrichItemsWithSubscriptionNames($items, $userId);

        $this->assertEquals('Feed One', $result[0]['source']);
        $this->assertEquals('Feed Two', $result[1]['source']);
        $this->assertArrayNotHasKey('source', $result[2]);
    }

    #[Test]
    public function refreshSubscriptionsUpdatesStatusOnSuccess(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription->expects($this->once())->method('updateLastRefreshedAt');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Success);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);
        $repository->expects($this->once())->method('flush');

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willReturn(['title' => 'Test', 'items' => [['id' => 1]]]);

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $count = $service->refreshSubscriptions($userId);

        $this->assertEquals(1, $count);
    }

    #[Test]
    public function refreshSubscriptionsSetsUnreachableOnHttpError(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription->expects($this->never())->method('updateLastRefreshedAt');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);
        $repository->expects($this->once())->method('flush');

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new \FeedIo\Adapter\NotFoundException('Not found'),
            );

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $count = $service->refreshSubscriptions($userId);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function refreshSubscriptionsSetsInvalidOnParserError(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription->expects($this->never())->method('updateLastRefreshedAt');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Invalid);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);
        $repository->expects($this->once())->method('flush');

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new \FeedIo\Reader\NoAccurateParserException('Invalid feed'),
            );

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $count = $service->refreshSubscriptions($userId);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function refreshSubscriptionsContinuesAfterFailure(): void
    {
        $userId = 1;

        $subscription1 = $this->createMock(Subscription::class);
        $subscription1
            ->method('getUrl')
            ->willReturn('https://example.com/feed1');
        $subscription1
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $subscription2 = $this->createMock(Subscription::class);
        $subscription2
            ->method('getUrl')
            ->willReturn('https://example.com/feed2');
        $subscription2->expects($this->once())->method('updateLastRefreshedAt');
        $subscription2
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Success);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->method('findByUserId')
            ->willReturn([$subscription1, $subscription2]);
        $repository->expects($this->exactly(2))->method('flush');

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willReturnCallback(function (string $url) {
                if ($url === 'https://example.com/feed1') {
                    throw new \FeedIo\Adapter\HttpRequestException('Error');
                }

                return ['title' => 'Test', 'items' => []];
            });

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $count = $service->refreshSubscriptions($userId);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function countByUserReturnsCount(): void
    {
        $userId = 1;

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('countByUserId')
            ->with($userId)
            ->willReturn(5);

        $service = $this->createService(subscriptionRepository: $repository);

        $this->assertEquals(5, $service->countByUser($userId));
    }

    #[Test]
    public function hasSubscriptionsReturnsTrueWhenSubscriptionsExist(): void
    {
        $userId = 1;

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('countByUserId')->willReturn(3);

        $service = $this->createService(subscriptionRepository: $repository);

        $this->assertTrue($service->hasSubscriptions($userId));
    }

    #[Test]
    public function hasSubscriptionsReturnsFalseWhenNoSubscriptions(): void
    {
        $userId = 1;

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method('countByUserId')->willReturn(0);

        $service = $this->createService(subscriptionRepository: $repository);

        $this->assertFalse($service->hasSubscriptions($userId));
    }

    #[Test]
    public function updateSubscriptionUpdatesNameAndFolder(): void
    {
        $userId = 1;
        $guid = 'test-guid';
        $name = 'New Name';
        $folder = 'Tech';

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects($this->once())->method('setName')->with($name);
        $subscription
            ->expects($this->once())
            ->method('setFolder')
            ->with($folder);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('findByGuid')
            ->with($userId, $guid)
            ->willReturn($subscription);
        $repository->expects($this->once())->method('flush');

        $service = $this->createService(subscriptionRepository: $repository);

        $service->updateSubscription($userId, $guid, $name, $folder);
    }

    #[Test]
    public function updateSubscriptionDoesNothingWhenNotFound(): void
    {
        $userId = 1;
        $guid = 'non-existent';

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('findByGuid')
            ->with($userId, $guid)
            ->willReturn(null);
        $repository->expects($this->never())->method('flush');

        $service = $this->createService(subscriptionRepository: $repository);

        $service->updateSubscription($userId, $guid, 'Name', null);
    }

    #[Test]
    public function getSubscriptionByGuidReturnsSubscription(): void
    {
        $userId = 1;
        $guid = 'test-guid';
        $subscription = $this->createSubscriptionStub(
            $guid,
            'Test Feed',
            'https://example.com/feed',
        );

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('findByGuid')
            ->with($userId, $guid)
            ->willReturn($subscription);

        $service = $this->createService(subscriptionRepository: $repository);

        $result = $service->getSubscriptionByGuid($userId, $guid);

        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function getOldestRefreshTimeReturnsTime(): void
    {
        $userId = 1;
        $time = new \DateTimeImmutable('2024-01-01 12:00:00');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method('getOldestRefreshTime')
            ->with($userId)
            ->willReturn($time);

        $service = $this->createService(subscriptionRepository: $repository);

        $result = $service->getOldestRefreshTime($userId);

        $this->assertSame($time, $result);
    }

    #[Test]
    public function updateRefreshTimestampUpdatesAndFlushes(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->expects($this->once())->method('updateLastRefreshedAt');

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects($this->once())->method('flush');

        $service = $this->createService(subscriptionRepository: $repository);

        $service->updateRefreshTimestamp($subscription);
    }

    #[Test]
    public function refreshSubscriptionsSetsTimeoutOnTimeoutError(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Timeout);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);
        $repository->expects($this->once())->method('flush');

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new \FeedIo\Adapter\HttpRequestException(
                    'Connection timed out',
                ),
            );

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $service->refreshSubscriptions($userId);
    }

    #[Test]
    public function refreshSubscriptionsSetsInvalidOnUnsupportedFormat(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Invalid);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new \FeedIo\Parser\UnsupportedFormatException('Unsupported'),
            );

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $service->refreshSubscriptions($userId);
    }

    #[Test]
    public function refreshSubscriptionsSetsInvalidOnMissingFields(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Invalid);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new \FeedIo\Parser\MissingFieldsException('Missing fields'),
            );

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $service->refreshSubscriptions($userId);
    }

    #[Test]
    public function refreshSubscriptionsSetsUnreachableOnServerError(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new \FeedIo\Adapter\ServerErrorException(
                    $this->createStub(
                        \Psr\Http\Message\ResponseInterface::class,
                    ),
                ),
            );

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $service->refreshSubscriptions($userId);
    }

    #[Test]
    public function refreshSubscriptionsSetsUnreachableOnGenericException(): void
    {
        $userId = 1;
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getUrl')->willReturn('https://example.com/feed');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findByUserId')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(new \Exception('Unknown error'));

        $service = $this->createService(
            subscriptionRepository: $repository,
            feedReaderService: $feedReaderService,
        );

        $service->refreshSubscriptions($userId);
    }

    private function createSubscriptionStub(
        string $guid,
        string $name,
        string $url,
        ?string $folder = null,
    ): Subscription {
        $subscription = $this->createStub(Subscription::class);
        $subscription->method('getGuid')->willReturn($guid);
        $subscription->method('getName')->willReturn($name);
        $subscription->method('getUrl')->willReturn($url);
        $subscription->method('getFolder')->willReturn($folder);

        return $subscription;
    }

    private function createService(
        ?SubscriptionRepository $subscriptionRepository = null,
        ?FeedReaderService $feedReaderService = null,
        ?FeedContentService $feedContentService = null,
        ?FeedItemRepository $feedItemRepository = null,
        ?ReadStatusRepository $readStatusRepository = null,
        ?SeenStatusRepository $seenStatusRepository = null,
        ?LoggerInterface $logger = null,
    ): SubscriptionService {
        return new SubscriptionService(
            $subscriptionRepository ??
                $this->createStub(SubscriptionRepository::class),
            $feedItemRepository ?? $this->createStub(FeedItemRepository::class),
            $readStatusRepository ??
                $this->createStub(ReadStatusRepository::class),
            $seenStatusRepository ??
                $this->createStub(SeenStatusRepository::class),
            $feedReaderService ?? $this->createStub(FeedReaderService::class),
            $feedContentService ?? $this->createStub(FeedContentService::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
