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

        // Get unread counts for sidebar (uses optimized query with word filter)
        $feeds = $this->subscriptionService->getSubscriptionsWithUnreadCounts(
            $userId,
            $filterWords,
        );

        // Get total unread count (with word filter applied)
        $unreadCounts = $this->feedItemRepository->getUnreadCountsBySubscription(
            $sguids,
            $userId,
            $filterWords,
        );
        $totalUnreadCount = array_sum($unreadCounts);

        // Load items with all filters applied at query level
        $items = $this->feedItemRepository->findItemsWithStatus(
            $sguids,
            $userId,
            $filterWords,
            $unreadOnly,
            $limit,
            $sguid,
            $fguid, // Exclude active item from unread filter
        );

        // Apply subscription names
        $nameMap = [];
        foreach ($subscriptions as $subscription) {
            $nameMap[$subscription->getGuid()] = $subscription->getName();
        }
        $items = array_map(function ($item) use ($nameMap) {
            if (isset($nameMap[$item['sguid']])) {
                $item['source'] = $nameMap[$item['sguid']];
            }

            return $item;
        }, $items);

        // Find active item
        $activeItem = $fguid ? $this->findItemByGuid($items, $fguid) : null;

        // Group feeds by folder
        $groupedFeeds = $this->groupFeedsByFolder($feeds);

        return [
            'feeds' => $feeds,
            'groupedFeeds' => $groupedFeeds['grouped'],
            'ungroupedFeeds' => $groupedFeeds['ungrouped'],
            'items' => $items,
            'allItemsCount' => $totalUnreadCount,
            'activeItem' => $activeItem,
        ];
    }

    /**
     * @param list<array<string, mixed>> $feeds
     *
     * @return array{grouped: array<string, list<array<string, mixed>>>, ungrouped: list<array<string, mixed>>}
     */
    private function groupFeedsByFolder(array $feeds): array
    {
        $grouped = [];
        $ungrouped = [];

        foreach ($feeds as $feed) {
            $folder = $feed['folder'] ?? null;
            if ($folder !== null && $folder !== '') {
                $grouped[$folder][] = $feed;
            } else {
                $ungrouped[] = $feed;
            }
        }

        ksort($grouped);

        return ['grouped' => $grouped, 'ungrouped' => $ungrouped];
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
        return $this->feedItemRepository->getItemGuidsBySubscription($sguid);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
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
