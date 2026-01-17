<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository;

/**
 * Criteria object for FeedItemRepository::findItemsWithStatus() query.
 *
 * @phpstan-type FilterWords list<string>
 * @phpstan-type SubscriptionGuids list<string>
 */
final readonly class FeedItemQueryCriteria
{
    /**
     * @param list<string> $subscriptionGuids       Required: subscription GUIDs to query items from
     * @param int          $userId                  Required: user ID for status associations
     * @param list<string> $filterWords             Words to exclude from title/excerpt
     * @param bool         $unreadOnly              Filter to unread items only
     * @param int          $limit                   Max results (0 = no limit)
     * @param string|null  $subscriptionGuid        Filter to specific subscription
     * @param string|null  $excludeFromUnreadFilter Exclude item from unread filter (shows active item even if read)
     * @param bool         $bookmarkedOnly          Filter to bookmarked items only
     */
    public function __construct(
        public array $subscriptionGuids,
        public int $userId,
        public array $filterWords = [],
        public bool $unreadOnly = false,
        public int $limit = 0,
        public ?string $subscriptionGuid = null,
        public ?string $excludeFromUnreadFilter = null,
        public bool $bookmarkedOnly = false,
    ) {
    }
}
