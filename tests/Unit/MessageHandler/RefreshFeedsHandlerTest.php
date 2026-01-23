<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\MessageHandler;

use App\Domain\Feed\Entity\Subscription;
use App\Domain\Feed\Repository\SubscriptionRepository;
use App\Domain\Feed\Service\FeedExceptionHandler;
use App\Domain\Feed\Service\FeedPersistenceService;
use App\Domain\Feed\Service\FeedReaderService;
use App\Enum\SubscriptionStatus;
use App\Message\RefreshFeedsMessage;
use App\MessageHandler\RefreshFeedsHandler;
use Doctrine\ORM\EntityManagerInterface;
use FeedIo\Adapter\HttpRequestException;
use FeedIo\Adapter\NotFoundException;
use FeedIo\Adapter\ServerErrorException;
use FeedIo\Parser\MissingFieldsException;
use FeedIo\Parser\UnsupportedFormatException;
use FeedIo\Reader\NoAccurateParserException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class RefreshFeedsHandlerTest extends TestCase
{
    private function createExceptionHandler(
        LoggerInterface $logger,
    ): FeedExceptionHandler {
        return new FeedExceptionHandler($logger);
    }

    private function createLockFactory(bool $acquirable = true): LockFactory
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn($acquirable);
        $lock->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        return $lockFactory;
    }

    private function createPersistenceService(): FeedPersistenceService
    {
        $service = $this->createMock(FeedPersistenceService::class);
        $service->method('deleteDuplicates')->willReturn(0);

        return $service;
    }

    #[Test]
    public function refreshesAllSubscriptionFeeds(): void
    {
        $subscription1 = $this->createMock(Subscription::class);
        $subscription1
            ->method('getUrl')
            ->willReturn('https://example.com/feed1.xml');
        $subscription1->expects($this->once())->method('updateLastRefreshedAt');

        $subscription2 = $this->createMock(Subscription::class);
        $subscription2
            ->method('getUrl')
            ->willReturn('https://example.com/feed2.xml');
        $subscription2->expects($this->once())->method('updateLastRefreshedAt');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository
            ->method('findAll')
            ->willReturn([$subscription1, $subscription2]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->expects($this->exactly(2))
            ->method('fetchAndPersistFeed')
            ->willReturnCallback(function (string $url) {
                return ['title' => 'Test', 'items' => []];
            });

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function handlesEmptySubscriptions(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->expects($this->never())
            ->method('fetchAndPersistFeed');

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function logsRefreshDetails(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([]);

        $feedReaderService = $this->createMock(FeedReaderService::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (
                string $message,
                array $context = [],
            ) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    $this->assertEquals('Refreshing feeds', $message);
                    $this->assertEquals(['count' => 0], $context);
                }

                if ($callCount === 2) {
                    $this->assertEquals('Feeds refreshed', $message);
                    $this->assertEquals(
                        [
                            'success' => 0,
                            'skipped' => 0,
                            'failed' => 0,
                            'duplicates_removed' => 0,
                        ],
                        $context,
                    );
                }
            });

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function continuesOnFailureAndUpdatesOnlySuccessful(): void
    {
        $subscription1 = $this->createMock(Subscription::class);
        $subscription1
            ->method('getUrl')
            ->willReturn('https://example.com/feed1.xml');
        $subscription1->expects($this->once())->method('updateLastRefreshedAt');
        $subscription1
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Success);

        $subscription2 = $this->createMock(Subscription::class);
        $subscription2
            ->method('getUrl')
            ->willReturn('https://example.com/feed2.xml');
        $subscription2
            ->expects($this->never())
            ->method('updateLastRefreshedAt');
        $subscription2
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $subscription3 = $this->createMock(Subscription::class);
        $subscription3
            ->method('getUrl')
            ->willReturn('https://example.com/feed3.xml');
        $subscription3->expects($this->once())->method('updateLastRefreshedAt');
        $subscription3
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Success);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(3))->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository
            ->method('findAll')
            ->willReturn([$subscription1, $subscription2, $subscription3]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->expects($this->exactly(3))
            ->method('fetchAndPersistFeed')
            ->willReturnCallback(function (string $url) {
                if ($url === 'https://example.com/feed2.xml') {
                    throw new \Exception('Feed unavailable');
                }

                return ['title' => 'Test', 'items' => []];
            });

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsTimeoutStatusOnTimeoutException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription->expects($this->never())->method('updateLastRefreshedAt');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Timeout);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new HttpRequestException('Connection timed out'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsUnreachableStatusOnHttpRequestException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new HttpRequestException('Connection refused'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsUnreachableStatusOnNotFoundException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(new NotFoundException('404 Not Found'));

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsUnreachableStatusOnServerErrorException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Unreachable);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new ServerErrorException(
                    $this->createStub(ResponseInterface::class),
                ),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsInvalidStatusOnNoAccurateParserException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Invalid);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new NoAccurateParserException('No parser found'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsInvalidStatusOnUnsupportedFormatException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Invalid);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new UnsupportedFormatException('Unsupported format'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function setsInvalidStatusOnMissingFieldsException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');
        $subscription
            ->expects($this->once())
            ->method('setStatus')
            ->with(SubscriptionStatus::Invalid);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(
                new MissingFieldsException('Missing required fields'),
            );

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function skipsSubscriptionWhenLockNotAcquired(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->expects($this->never())
            ->method('fetchAndPersistFeed');

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $this->createLockFactory(acquirable: false),
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function releasesLockAfterRefresh(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willReturn(['title' => 'Test', 'items' => []]);

        $logger = $this->createMock(LoggerInterface::class);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $lockFactory,
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function releasesLockEvenOnException(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getId')->willReturn(1);
        $subscription
            ->method('getUrl')
            ->willReturn('https://example.com/feed.xml');

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([$subscription]);

        $feedReaderService = $this->createMock(FeedReaderService::class);
        $feedReaderService
            ->method('fetchAndPersistFeed')
            ->willThrowException(new \Exception('Feed error'));

        $logger = $this->createMock(LoggerInterface::class);

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->willReturn($lock);

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $this->createPersistenceService(),
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
            $lockFactory,
        );
        $handler(new RefreshFeedsMessage());
    }
}
