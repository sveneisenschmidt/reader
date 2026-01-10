<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Repository\Content\FeedItemRepository;
use App\Repository\Messages\ProcessedMessageRepository;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Repository\Users\ReadStatusRepository;
use App\Repository\Users\SeenStatusRepository;

class StatusService
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private FeedItemRepository $feedItemRepository,
        private ReadStatusRepository $readStatusRepository,
        private SeenStatusRepository $seenStatusRepository,
        private ProcessedMessageRepository $processedMessageRepository,
    ) {
    }

    /**
     * @return array{
     *     subscriptions: list<array{
     *         name: string,
     *         guid: string,
     *         status: string,
     *         lastRefreshedAt: ?\DateTimeImmutable,
     *         itemCount: int,
     *         readCount: int,
     *         unreadCount: int,
     *         seenCount: int,
     *         unseenCount: int
     *     }>,
     *     totals: array{
     *         subscriptions: int,
     *         items: int,
     *         read: int,
     *         unread: int,
     *         seen: int,
     *         unseen: int
     *     }
     * }
     */
    public function getSubscriptionStats(int $userId): array
    {
        $subscriptions = $this->subscriptionRepository->findByUserId($userId);
        $readGuids = $this->readStatusRepository->getReadGuidsForUser($userId);
        $seenGuids = $this->seenStatusRepository->getSeenGuidsForUser($userId);

        $readGuidsSet = array_flip($readGuids);
        $seenGuidsSet = array_flip($seenGuids);

        $stats = [];
        $totals = [
            'subscriptions' => count($subscriptions),
            'items' => 0,
            'read' => 0,
            'unread' => 0,
            'seen' => 0,
            'unseen' => 0,
        ];

        foreach ($subscriptions as $subscription) {
            $itemGuids = $this->feedItemRepository->getGuidsByFeedGuid(
                $subscription->getGuid(),
            );
            $itemCount = count($itemGuids);

            $readCount = 0;
            $seenCount = 0;

            foreach ($itemGuids as $guid) {
                if (isset($readGuidsSet[$guid])) {
                    ++$readCount;
                }
                if (isset($seenGuidsSet[$guid])) {
                    ++$seenCount;
                }
            }

            $unreadCount = $itemCount - $readCount;
            $unseenCount = $itemCount - $seenCount;

            $stats[] = [
                'name' => $subscription->getName(),
                'guid' => $subscription->getGuid(),
                'status' => $subscription->getStatus()->value,
                'lastRefreshedAt' => $subscription->getLastRefreshedAt(),
                'itemCount' => $itemCount,
                'readCount' => $readCount,
                'unreadCount' => $unreadCount,
                'seenCount' => $seenCount,
                'unseenCount' => $unseenCount,
            ];

            $totals['items'] += $itemCount;
            $totals['read'] += $readCount;
            $totals['unread'] += $unreadCount;
            $totals['seen'] += $seenCount;
            $totals['unseen'] += $unseenCount;
        }

        return [
            'subscriptions' => $stats,
            'totals' => $totals,
        ];
    }

    /**
     * @return array<string, array<string|null, int>>
     */
    public function getProcessedMessageCountsBySource(): array
    {
        return $this->processedMessageRepository->getCountsByTypeAndSource();
    }
}
