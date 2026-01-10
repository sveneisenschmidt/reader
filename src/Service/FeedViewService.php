<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class FeedViewService
{
    public function __construct(
        private FeedPersistenceService $feedPersistenceService,
        private SubscriptionService $subscriptionService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
    ) {
    }

    #[Returns('array<string, mixed>')]
    public function getViewData(
        int $userId,
        ?string $sguid = null,
        ?string $fguid = null,
        bool $unreadOnly = false,
        int $limit = 50,
    ): array {
        $allItems = $this->loadEnrichedItems($userId);
        $feeds = $this->subscriptionService->getSubscriptionsWithCounts(
            $userId,
            $allItems,
        );
        $unreadCount = count(
            array_filter($allItems, fn ($item) => !$item['isRead']),
        );

        $items = $sguid
            ? array_values(
                array_filter($allItems, fn ($item) => $item['sguid'] === $sguid),
            )
            : $allItems;

        $activeItem = $fguid ? $this->findItemByGuid($items, $fguid) : null;

        if ($unreadOnly) {
            $items = array_values(
                array_filter($items, fn ($item) => !$item['isRead']),
            );
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

    #[Returns('list<array<string, mixed>>')]
    public function loadEnrichedItems(int $userId): array
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedPersistenceService->getAllItems($sguids);
        $items = $this->subscriptionService->enrichItemsWithSubscriptionNames(
            $items,
            $userId,
        );
        $items = $this->readStatusService->enrichItemsWithReadStatus(
            $items,
            $userId,
        );

        return $this->seenStatusService->enrichItemsWithSeenStatus(
            $items,
            $userId,
        );
    }

    #[Returns('list<string>')]
    public function getAllItemGuids(int $userId): array
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedPersistenceService->getAllItems($sguids);

        return array_column($items, 'guid');
    }

    #[Returns('list<string>')]
    public function getItemGuidsForSubscription(
        int $userId,
        string $sguid,
    ): array {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedPersistenceService->getAllItems($sguids);
        $items = array_filter($items, fn ($item) => $item['sguid'] === $sguid);

        return array_column($items, 'guid');
    }

    public function findNextItemGuid(
        int $userId,
        ?string $sguid,
        string $currentGuid,
    ): ?string {
        $items = $this->getFilteredItems($userId, $sguid);

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

    public function findNextUnreadItemGuid(
        int $userId,
        ?string $sguid,
        string $currentGuid,
    ): ?string {
        $items = $this->getFilteredItems($userId, $sguid);
        $items = $this->readStatusService->enrichItemsWithReadStatus(
            $items,
            $userId,
        );

        $found = false;
        foreach ($items as $item) {
            if ($found && !$item['isRead']) {
                return $item['guid'];
            }
            if ($item['guid'] === $currentGuid) {
                $found = true;
            }
        }

        return null;
    }

    #[Returns('list<array<string, mixed>>')]
    private function getFilteredItems(int $userId, ?string $sguid): array
    {
        $sguids = $this->subscriptionService->getFeedGuids($userId);
        $items = $this->feedPersistenceService->getAllItems($sguids);

        if ($sguid) {
            $items = array_values(
                array_filter($items, fn ($item) => $item['sguid'] === $sguid),
            );
        }

        return $items;
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
