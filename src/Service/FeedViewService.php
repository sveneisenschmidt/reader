<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\EventSubscriber\FilterParameterSubscriber;
use App\Repository\FeedItemRepository;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class FeedViewService
{
    public function __construct(
        private FeedItemRepository $feedItemRepository,
        private SubscriptionService $subscriptionService,
        private UserPreferenceService $userPreferenceService,
    ) {
    }

    #[Returns('array<string, mixed>')]
    public function getViewData(
        int $userId,
        ?string $sguid = null,
        ?string $fguid = null,
        bool $unreadOnly = FilterParameterSubscriber::DEFAULT_UNREAD,
        int $limit = FilterParameterSubscriber::DEFAULT_LIMIT,
    ): array {
        $subscriptions = $this->subscriptionService->getSubscriptionsForUser(
            $userId,
        );
        $sguids = array_map(fn ($s) => $s->getGuid(), $subscriptions);
        $filterWords = $this->userPreferenceService->getFilterWords($userId);

        // Build subscription name map
        $nameMap = [];
        foreach ($subscriptions as $subscription) {
            $nameMap[$subscription->getGuid()] = $subscription->getName();
        }

        // Load all items (without limit/unread filter) for sidebar counts
        $allItems = $this->feedItemRepository->findItemsWithStatus(
            $sguids,
            $userId,
            $filterWords,
        );

        // Apply subscription names
        $allItems = array_map(function ($item) use ($nameMap) {
            if (isset($nameMap[$item['sguid']])) {
                $item['source'] = $nameMap[$item['sguid']];
            }

            return $item;
        }, $allItems);

        $feeds = $this->subscriptionService->getSubscriptionsWithCounts(
            $userId,
            $allItems,
        );
        $unreadCount = count(
            array_filter($allItems, fn ($item) => !$item['isRead']),
        );

        // Filter by subscription if specified
        $items = $sguid
            ? array_values(
                array_filter($allItems, fn ($item) => $item['sguid'] === $sguid),
            )
            : $allItems;

        $activeItem = $fguid ? $this->findItemByGuid($items, $fguid) : null;

        // Apply unread filter
        if ($unreadOnly) {
            $items = array_values(
                array_filter(
                    $items,
                    fn ($item) => !$item['isRead'] || $item['guid'] === $fguid,
                ),
            );
        }

        // Apply limit
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        return [
            'feeds' => $feeds,
            'items' => $items,
            'allItemsCount' => $unreadCount,
            'activeItem' => $activeItem,
        ];
    }

    #[Returns('list<string>')]
    public function getAllItemGuids(int $userId): array
    {
        $sguids = $this->subscriptionService->getSubscriptionGuids($userId);
        $items = $this->feedItemRepository->findItemsWithStatus(
            $sguids,
            $userId,
        );

        return array_column($items, 'guid');
    }

    #[Returns('list<string>')]
    public function getItemGuidsForSubscription(
        int $userId,
        string $sguid,
    ): array {
        $sguids = $this->subscriptionService->getSubscriptionGuids($userId);
        $items = $this->feedItemRepository->findItemsWithStatus(
            $sguids,
            $userId,
        );
        $items = array_filter($items, fn ($item) => $item['sguid'] === $sguid);

        return array_column($items, 'guid');
    }

    #[Param(items: 'list<array<string, mixed>>')]
    #[Returns('array<string, mixed>|null')]
    private function findItemByGuid(array $items, string $guid): ?array
    {
        foreach ($items as $item) {
            if ($item['guid'] === $guid) {
                return $item;
            }
        }

        return null;
    }
}
