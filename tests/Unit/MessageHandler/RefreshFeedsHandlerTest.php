<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Subscriptions\Subscription;
use App\Enum\SubscriptionStatus;
use App\Message\RefreshFeedsMessage;
use App\MessageHandler\RefreshFeedsHandler;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Service\FeedExceptionHandler;
use App\Service\FeedReaderService;
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

class RefreshFeedsHandlerTest extends TestCase
{
    private function createExceptionHandler(
        LoggerInterface $logger,
    ): FeedExceptionHandler {
        return new FeedExceptionHandler($logger);
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
                        ['success' => 0, 'failed' => 0],
                        $context,
                    );
                }
            });

        $handler = new RefreshFeedsHandler(
            $feedReaderService,
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
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
            $subscriptionRepository,
            $entityManager,
            $logger,
            $this->createExceptionHandler($logger),
        );
        $handler(new RefreshFeedsMessage());
    }
}
