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
use App\Message\RefreshFeedsMessage;
use App\MessageHandler\RefreshFeedsHandler;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Service\FeedFetcher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RefreshFeedsHandlerTest extends TestCase
{
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
        $entityManager->expects($this->once())->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository
            ->method('findAll')
            ->willReturn([$subscription1, $subscription2]);

        $feedFetcher = $this->createMock(FeedFetcher::class);
        $feedFetcher
            ->expects($this->once())
            ->method('refreshAllFeeds')
            ->with([
                'https://example.com/feed1.xml',
                'https://example.com/feed2.xml',
            ]);

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedFetcher,
            $subscriptionRepository,
            $entityManager,
            $logger,
        );
        $handler(new RefreshFeedsMessage());
    }

    #[Test]
    public function handlesEmptySubscriptions(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findAll')->willReturn([]);

        $feedFetcher = $this->createMock(FeedFetcher::class);
        $feedFetcher
            ->expects($this->once())
            ->method('refreshAllFeeds')
            ->with([]);

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new RefreshFeedsHandler(
            $feedFetcher,
            $subscriptionRepository,
            $entityManager,
            $logger,
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

        $feedFetcher = $this->createMock(FeedFetcher::class);

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
                    $this->assertEquals(
                        'Feeds refreshed successfully',
                        $message,
                    );
                }
            });

        $handler = new RefreshFeedsHandler(
            $feedFetcher,
            $subscriptionRepository,
            $entityManager,
            $logger,
        );
        $handler(new RefreshFeedsMessage());
    }
}
