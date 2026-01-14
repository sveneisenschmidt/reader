<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Subscription;
use App\Repository\FeedItemRepository;
use App\Repository\ReadStatusRepository;
use App\Repository\SeenStatusRepository;
use App\Repository\SubscriptionRepository;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class SubscriptionService
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private FeedItemRepository $feedItemRepository,
        private ReadStatusRepository $readStatusRepository,
        private SeenStatusRepository $seenStatusRepository,
        private FeedReaderService $feedReaderService,
    ) {
    }

    #[Returns('list<Subscription>')]
    public function getSubscriptionsForUser(int $userId): array
    {
        return $this->subscriptionRepository->findByUserId($userId);
    }

    public function countByUser(int $userId): int
    {
        return $this->subscriptionRepository->countByUserId($userId);
    }

    public function hasSubscriptions(int $userId): bool
    {
        return $this->countByUser($userId) > 0;
    }

    #[Param(items: 'list<array<string, mixed>>')]
    #[Returns('list<array<string, mixed>>')]
    public function getSubscriptionsWithCounts(
        int $userId,
        array $items = [],
    ): array {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        $result = [];

        // Count unread items per subscription
        $unreadCounts = [];
        foreach ($items as $item) {
            if (!isset($item['isRead']) || !$item['isRead']) {
                $sguid = $item['sguid'] ?? '';
                $unreadCounts[$sguid] = ($unreadCounts[$sguid] ?? 0) + 1;
            }
        }

        foreach ($subscriptions as $subscription) {
            $sguid = $subscription->getGuid();
            $result[] = [
                'sguid' => $sguid,
                'name' => $subscription->getName(),
                'url' => $subscription->getUrl(),
                'count' => $unreadCounts[$sguid] ?? 0,
                'folder' => $subscription->getFolder(),
            ];
        }

        return $result;
    }

    #[Param(filterWords: 'list<string>')]
    #[Returns('list<array<string, mixed>>')]
    public function getSubscriptionsWithUnreadCounts(
        int $userId,
        array $filterWords = [],
    ): array {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        $sguids = array_map(
            fn (Subscription $s) => $s->getGuid(),
            $subscriptions,
        );

        // Get unread counts from database query (with word filter applied)
        $unreadCounts = $this->feedItemRepository->getUnreadCountsBySubscription(
            $sguids,
            $userId,
            $filterWords,
        );

        $result = [];
        foreach ($subscriptions as $subscription) {
            $sguid = $subscription->getGuid();
            $result[] = [
                'sguid' => $sguid,
                'name' => $subscription->getName(),
                'url' => $subscription->getUrl(),
                'count' => $unreadCounts[$sguid] ?? 0,
                'folder' => $subscription->getFolder(),
            ];
        }

        return $result;
    }

    #[Returns('list<string>')]
    public function getFeedUrls(int $userId): array
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);

        return array_map(fn (Subscription $s) => $s->getUrl(), $subscriptions);
    }

    #[Returns('list<string>')]
    public function getSubscriptionGuids(int $userId): array
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);

        return array_map(fn (Subscription $s) => $s->getGuid(), $subscriptions);
    }

    public function addSubscription(int $userId, string $url): Subscription
    {
        $feedData = $this->feedReaderService->fetchAndPersistFeed($url);
        $guid = $this->createSubscriptionGuid($url);

        return $this->subscriptionRepository->addSubscription(
            $userId,
            $url,
            $feedData['title'],
            $guid,
        );
    }

    private function createSubscriptionGuid(string $feedUrl): string
    {
        return substr(hash('sha256', $feedUrl), 0, 16);
    }

    public function removeSubscription(int $userId, string $guid): void
    {
        // Get all feed item GUIDs for this subscription
        $feedItemGuids = $this->feedItemRepository->getGuidsBySubscriptionGuid(
            $guid,
        );

        // Delete read/seen statuses for these items
        if (!empty($feedItemGuids)) {
            $this->readStatusRepository->deleteByFeedItemGuids(
                $userId,
                $feedItemGuids,
            );
            $this->seenStatusRepository->deleteByFeedItemGuids(
                $userId,
                $feedItemGuids,
            );
        }

        // Delete all feed items for this subscription
        $this->feedItemRepository->deleteBySubscriptionGuid($guid);

        // Delete the subscription itself
        $this->subscriptionRepository->removeSubscription($userId, $guid);
    }

    public function updateSubscriptionName(
        int $userId,
        string $guid,
        string $name,
    ): void {
        $this->subscriptionRepository->updateName($userId, $guid, $name);
    }

    public function updateSubscription(
        int $userId,
        string $guid,
        string $name,
        ?string $folder,
    ): void {
        $subscription = $this->subscriptionRepository->findByGuid(
            $userId,
            $guid,
        );
        if ($subscription) {
            $subscription->setName($name);
            $subscription->setFolder($folder);
            $this->subscriptionRepository->flush();
        }
    }

    public function getSubscriptionByGuid(
        int $userId,
        string $guid,
    ): ?Subscription {
        return $this->subscriptionRepository->findByGuid($userId, $guid);
    }

    #[Param(items: 'list<array<string, mixed>>')]
    #[Returns('list<array<string, mixed>>')]
    public function enrichItemsWithSubscriptionNames(
        array $items,
        int $userId,
    ): array {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        $nameMap = [];

        foreach ($subscriptions as $subscription) {
            $nameMap[$subscription->getGuid()] = $subscription->getName();
        }

        return array_map(function ($item) use ($nameMap) {
            if (isset($nameMap[$item['sguid']])) {
                $item['source'] = $nameMap[$item['sguid']];
            }

            return $item;
        }, $items);
    }

    public function getLatestRefreshTime(int $userId): ?\DateTimeImmutable
    {
        return $this->subscriptionRepository->getLatestRefreshTime($userId);
    }

    public function updateRefreshTimestamp(Subscription $subscription): void
    {
        $subscription->updateLastRefreshedAt();
        $this->subscriptionRepository->flush();
    }
}
