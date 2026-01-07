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
use App\Repository\Subscriptions\SubscriptionRepository;

class SubscriptionService
{
    private const BLOCKED_HOSTS = [
        "localhost",
        "127.0.0.1",
        "0.0.0.0",
        "::1",
        "169.254.",
        "10.",
        "172.16.",
        "172.17.",
        "172.18.",
        "172.19.",
        "172.20.",
        "172.21.",
        "172.22.",
        "172.23.",
        "172.24.",
        "172.25.",
        "172.26.",
        "172.27.",
        "172.28.",
        "172.29.",
        "172.30.",
        "172.31.",
        "192.168.",
    ];

    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private FeedFetcher $feedFetcher,
        private string $appEnv = "prod",
    ) {}

    private function validateFeedUrl(string $url, int $index): void
    {
        $parsed = parse_url($url);

        if (
            $parsed === false ||
            !isset($parsed["scheme"]) ||
            !isset($parsed["host"])
        ) {
            throw new \InvalidArgumentException("Item $index has invalid URL");
        }

        if (!in_array($parsed["scheme"], ["http", "https"], true)) {
            throw new \InvalidArgumentException(
                "Item $index URL must use http or https",
            );
        }

        $host = strtolower($parsed["host"]);

        // Allow localhost in dev mode
        if ($this->appEnv === "dev") {
            return;
        }

        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($host === $blocked || str_starts_with($host, $blocked)) {
                throw new \InvalidArgumentException(
                    "Item $index URL points to blocked host",
                );
            }
        }
    }

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
        $this->subscriptionRepository->removeSubscription($userId, $guid);
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

    public function toYaml(int $userId): string
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        $data = [];

        foreach ($subscriptions as $subscription) {
            $item = [
                "url" => $subscription->getUrl(),
                "title" => $subscription->getName(),
            ];
            if ($subscription->getFolder() !== null) {
                $item["folder"] = $subscription->getFolder();
            }
            $data[] = $item;
        }

        return \Symfony\Component\Yaml\Yaml::dump($data);
    }

    public function getOldestRefreshTime(int $userId): ?\DateTimeImmutable
    {
        $subscriptions = $this->getSubscriptionsForUser($userId);
        $oldest = null;

        foreach ($subscriptions as $subscription) {
            $lastRefreshed = $subscription->getLastRefreshedAt();
            if ($lastRefreshed === null) {
                return null;
            }
            if ($oldest === null || $lastRefreshed < $oldest) {
                $oldest = $lastRefreshed;
            }
        }

        return $oldest;
    }

    public function updateRefreshTimestamps(int $userId): void
    {
        $this->subscriptionRepository->updateAllRefreshTimestamps($userId);
    }

    public function importFromYaml(int $userId, string $yaml): void
    {
        try {
            $data = \Symfony\Component\Yaml\Yaml::parse($yaml);
        } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
            throw new \InvalidArgumentException(
                "Invalid YAML syntax: " . $e->getMessage(),
            );
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException(
                "YAML must be a list of subscriptions",
            );
        }

        foreach ($data as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException(
                    "Item $index must be an object",
                );
            }
            if (!isset($item["url"]) || !is_string($item["url"])) {
                throw new \InvalidArgumentException(
                    "Item $index must have a 'url' string",
                );
            }
            if (isset($item["title"]) && !is_string($item["title"])) {
                throw new \InvalidArgumentException(
                    "Item $index 'title' must be a string",
                );
            }
            if (isset($item["folder"]) && !is_array($item["folder"])) {
                throw new \InvalidArgumentException(
                    "Item $index 'folder' must be an array",
                );
            }
            if (isset($item["folder"])) {
                foreach ($item["folder"] as $folderPart) {
                    if (!is_string($folderPart)) {
                        throw new \InvalidArgumentException(
                            "Item $index 'folder' must contain only strings",
                        );
                    }
                }
            }
            $this->validateFeedUrl($item["url"], $index);
        }

        // Get current subscriptions
        $currentSubs = $this->subscriptionRepository->findByUserId($userId);
        $currentGuids = array_map(fn($s) => $s->getGuid(), $currentSubs);

        $newGuids = [];

        foreach ($data as $item) {
            if (!isset($item["url"])) {
                continue;
            }

            $url = $item["url"];
            $sguid = $this->feedFetcher->createGuid($url);
            $newGuids[] = $sguid;

            $existing = $this->subscriptionRepository->findByGuid(
                $userId,
                $sguid,
            );

            if ($existing) {
                // Update title if provided
                if (isset($item["title"])) {
                    $this->subscriptionRepository->updateName(
                        $userId,
                        $sguid,
                        $item["title"],
                    );
                }
                // Update folder
                $this->subscriptionRepository->updateFolder(
                    $userId,
                    $sguid,
                    $item["folder"] ?? null,
                );
            } else {
                // Add new subscription
                $title =
                    $item["title"] ?? $this->feedFetcher->getFeedTitle($url);
                $subscription = $this->subscriptionRepository->addSubscription(
                    $userId,
                    $url,
                    $title,
                    $sguid,
                );
                if (isset($item["folder"])) {
                    $subscription->setFolder($item["folder"]);
                    $this->subscriptionRepository->getEntityManager()->flush();
                }
            }
        }

        // Remove subscriptions not in YAML
        foreach ($currentGuids as $guid) {
            if (!in_array($guid, $newGuids)) {
                $this->subscriptionRepository->removeSubscription(
                    $userId,
                    $guid,
                );
            }
        }
    }
}
