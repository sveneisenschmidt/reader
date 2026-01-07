<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Subscriptions\Subscription;
use App\Repository\Content\FeedItemRepository;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Repository\Users\ReadStatusRepository;
use App\Repository\Users\SeenStatusRepository;

class SubscriptionService
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private FeedItemRepository $feedItemRepository,
        private ReadStatusRepository $readStatusRepository,
        private SeenStatusRepository $seenStatusRepository,
        private FeedFetcher $feedFetcher,
    ) {}

    public function getSubscriptionsForUser(int $userId): array
    {
        return $this->subscriptionRepository->findByUserId($userId);
    }

    public function countByUser(int $userId): int
    {
        return $this->subscriptionRepository->countByUserId($userId);
    }

    public function getSubscriptionsWithCounts(
        int $userId,
        array $items = [],
    ): array {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        $result = [];

        // Count unread items per subscription
        $unreadCounts = [];
        foreach ($items as $item) {
            if (!isset($item["isRead"]) || !$item["isRead"]) {
                $sguid = $item["sguid"] ?? "";
                $unreadCounts[$sguid] = ($unreadCounts[$sguid] ?? 0) + 1;
            }
        }

        foreach ($subscriptions as $subscription) {
            $sguid = $subscription->getGuid();
            $result[] = [
                "sguid" => $sguid,
                "name" => $subscription->getName(),
                "url" => $subscription->getUrl(),
                "count" => $unreadCounts[$sguid] ?? 0,
                "folder" => $subscription->getFolder(),
            ];
        }

        return $result;
    }

    public function getFeedUrls(int $userId): array
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        return array_map(fn(Subscription $s) => $s->getUrl(), $subscriptions);
    }

    public function getFeedGuids(int $userId): array
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        return array_map(fn(Subscription $s) => $s->getGuid(), $subscriptions);
    }

    public function addSubscription(int $userId, string $url): Subscription
    {
        $title = $this->feedFetcher->getFeedTitle($url);
        $guid = $this->feedFetcher->createGuid($url);

        return $this->subscriptionRepository->addSubscription(
            $userId,
            $url,
            $title,
            $guid,
        );
    }

    public function removeSubscription(int $userId, string $guid): void
    {
        // Get all feed item GUIDs for this subscription
        $feedItemGuids = $this->feedItemRepository->getGuidsByFeedGuid($guid);

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
        $this->feedItemRepository->deleteByFeedGuid($guid);

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

    public function getSubscriptionByGuid(
        int $userId,
        string $guid,
    ): ?Subscription {
        return $this->subscriptionRepository->findByGuid($userId, $guid);
    }

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
            if (isset($nameMap[$item["sguid"]])) {
                $item["source"] = $nameMap[$item["sguid"]];
            }
            return $item;
        }, $items);
    }

    public function getOldestRefreshTime(int $userId): ?\DateTimeImmutable
    {
        return $this->subscriptionRepository->getOldestRefreshTime($userId);
    }

    public function updateRefreshTimestamps(int $userId): void
    {
        $this->subscriptionRepository->updateAllRefreshTimestamps($userId);
    }
}
