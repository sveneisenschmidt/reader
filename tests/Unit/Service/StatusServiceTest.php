<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\Feed\Entity\Subscription;
use App\Domain\Feed\Repository\FeedItemRepository;
use App\Domain\Feed\Repository\SubscriptionRepository;
use App\Domain\ItemStatus\Repository\ReadStatusRepository;
use App\Domain\ItemStatus\Repository\SeenStatusRepository;
use App\Enum\SubscriptionStatus;
use App\Repository\ProcessedMessageRepository;
use App\Service\StatusService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatusServiceTest extends TestCase
{
    #[Test]
    public function getSubscriptionStatsReturnsEmptyArrayWhenNoSubscriptions(): void
    {
        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository->method('findByUserId')->willReturn([]);

        $readStatusRepository = $this->createMock(ReadStatusRepository::class);
        $readStatusRepository->method('getReadGuidsForUser')->willReturn([]);

        $seenStatusRepository = $this->createMock(SeenStatusRepository::class);
        $seenStatusRepository->method('getSeenGuidsForUser')->willReturn([]);

        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $processedMessageRepository = $this->createMock(
            ProcessedMessageRepository::class,
        );

        $service = new StatusService(
            $subscriptionRepository,
            $feedItemRepository,
            $readStatusRepository,
            $seenStatusRepository,
            $processedMessageRepository,
        );

        $result = $service->getSubscriptionStats(1);

        $this->assertSame([], $result['subscriptions']);
        $this->assertSame(0, $result['totals']['subscriptions']);
        $this->assertSame(0, $result['totals']['items']);
    }

    #[Test]
    public function getSubscriptionStatsCalculatesCorrectCounts(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getName')->willReturn('Test Feed');
        $subscription->method('getGuid')->willReturn('feed-guid');
        $subscription
            ->method('getStatus')
            ->willReturn(SubscriptionStatus::Success);
        $subscription
            ->method('getLastRefreshedAt')
            ->willReturn(new \DateTimeImmutable());

        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $subscriptionRepository
            ->method('findByUserId')
            ->willReturn([$subscription]);

        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->method('getGuidsBySubscriptionGuid')
            ->willReturn(['item-1', 'item-2', 'item-3']);

        $readStatusRepository = $this->createMock(ReadStatusRepository::class);
        $readStatusRepository
            ->method('getReadGuidsForUser')
            ->willReturn(['item-1']);

        $seenStatusRepository = $this->createMock(SeenStatusRepository::class);
        $seenStatusRepository
            ->method('getSeenGuidsForUser')
            ->willReturn(['item-1', 'item-2']);

        $processedMessageRepository = $this->createMock(
            ProcessedMessageRepository::class,
        );

        $service = new StatusService(
            $subscriptionRepository,
            $feedItemRepository,
            $readStatusRepository,
            $seenStatusRepository,
            $processedMessageRepository,
        );

        $result = $service->getSubscriptionStats(1);

        $this->assertCount(1, $result['subscriptions']);
        $this->assertSame('Test Feed', $result['subscriptions'][0]['name']);
        $this->assertSame(3, $result['subscriptions'][0]['itemCount']);
        $this->assertSame(1, $result['subscriptions'][0]['readCount']);
        $this->assertSame(2, $result['subscriptions'][0]['unreadCount']);
        $this->assertSame(2, $result['subscriptions'][0]['seenCount']);
        $this->assertSame(1, $result['subscriptions'][0]['unseenCount']);

        $this->assertSame(1, $result['totals']['subscriptions']);
        $this->assertSame(3, $result['totals']['items']);
        $this->assertSame(1, $result['totals']['read']);
        $this->assertSame(2, $result['totals']['unread']);
    }

    #[Test]
    public function getProcessedMessageCountsBySourceReturnsRepositoryResult(): void
    {
        $subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $readStatusRepository = $this->createMock(ReadStatusRepository::class);
        $seenStatusRepository = $this->createMock(SeenStatusRepository::class);

        $now = new \DateTimeImmutable();
        $processedMessageRepository = $this->createMock(
            ProcessedMessageRepository::class,
        );
        $processedMessageRepository
            ->method('getCountsByTypeAndSource')
            ->willReturn([
                'App\\Message\\HeartbeatMessage' => [
                    'worker' => ['count' => 100, 'lastProcessedAt' => $now],
                ],
                'App\\Message\\RefreshFeedsMessage' => [
                    'worker' => ['count' => 30, 'lastProcessedAt' => $now],
                    'webhook' => ['count' => 15, 'lastProcessedAt' => $now],
                    'manual' => ['count' => 5, 'lastProcessedAt' => $now],
                ],
            ]);

        $service = new StatusService(
            $subscriptionRepository,
            $feedItemRepository,
            $readStatusRepository,
            $seenStatusRepository,
            $processedMessageRepository,
        );

        $result = $service->getProcessedMessageCountsBySource();

        $this->assertSame(
            100,
            $result['App\\Message\\HeartbeatMessage']['worker']['count'],
        );
        $this->assertSame(
            30,
            $result['App\\Message\\RefreshFeedsMessage']['worker']['count'],
        );
        $this->assertSame(
            15,
            $result['App\\Message\\RefreshFeedsMessage']['webhook']['count'],
        );
        $this->assertSame(
            5,
            $result['App\\Message\\RefreshFeedsMessage']['manual']['count'],
        );
    }
}
