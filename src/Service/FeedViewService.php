<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

class FeedViewService
{
    public function __construct(
        private FeedFetcher $feedFetcher,
        private SubscriptionService $subscriptionService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
    ) {}

    public function getViewData(
        int $userId,
        ?string $sguid = null,
        ?string $fguid = null,
        bool $unreadOnly = false,
        int $limit = 100,
    ): array {
        $allItems = $this->loadEnrichedItems($userId);
        $feeds = $this->subscriptionService->getSubscriptionsWithCounts($userId, $allItems);
        $unreadCount = count(array_filter($allItems, fn($item) => !$item['isRead']));

        $items = $sguid
            ? array_values(array_filter($allItems, fn($item) => $item['sguid'] === $sguid))
            : $allItems;

        $activeItem = $fguid ? $this->findItemByGuid($items, $fguid) : null;

        if ($unreadOnly) {
            $items = array_values(array_filter($items, fn($item) => !$item['isRead']));
        }

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

    public function loadEnrichedItems(int $userId): array
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedFetcher->getAllItems($sguids);
        $items = $this->subscriptionService->enrichItemsWithSubscriptionNames($items, $userId);
        $items = $this->readStatusService->enrichItemsWithReadStatus($items, $userId);

        return $this->seenStatusService->enrichItemsWithSeenStatus($items, $userId);
    }

    public function getAllItemGuids(int $userId): array
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedFetcher->getAllItems($sguids);

        return array_column($items, 'guid');
    }

    public function getItemGuidsForSubscription(int $userId, string $sguid): array
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedFetcher->getAllItems($sguids);
        $items = array_filter($items, fn($item) => $item['sguid'] === $sguid);

        return array_column($items, 'guid');
    }

    public function findNextItemGuid(int $userId, ?string $sguid, string $currentGuid): ?string
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedFetcher->getAllItems($sguids);

        if ($sguid) {
            $items = array_values(array_filter($items, fn($item) => $item['sguid'] === $sguid));
        }

        $found = false;
        foreach ($items as $item) {
            if ($found) {
                return $item['guid'];
            }
            if ($item['guid'] === $currentGuid) {
                $found = true;
            }
        }

        return null;
    }

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
