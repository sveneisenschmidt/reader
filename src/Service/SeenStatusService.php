<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Repository\Users\SeenStatusRepository;

class SeenStatusService
{
    public function __construct(
        private SeenStatusRepository $seenStatusRepository,
    ) {
    }

    public function markAsSeen(int $userId, string $feedItemGuid): void
    {
        $this->seenStatusRepository->markAsSeen($userId, $feedItemGuid);
    }

    public function markManyAsSeen(int $userId, array $feedItemGuids): void
    {
        $this->seenStatusRepository->markManyAsSeen($userId, $feedItemGuids);
    }

    public function isSeen(int $userId, string $feedItemGuid): bool
    {
        return $this->seenStatusRepository->isSeen($userId, $feedItemGuid);
    }

    public function getSeenGuidsForUser(int $userId, array $filterGuids = []): array
    {
        return $this->seenStatusRepository->getSeenGuidsForUser($userId, $filterGuids);
    }

    public function enrichItemsWithSeenStatus(array $items, int $userId): array
    {
        $guids = array_column($items, 'guid');
        $seenGuids = $this->getSeenGuidsForUser($userId, $guids);

        return array_map(function ($item) use ($seenGuids) {
            $item['isNew'] = !in_array($item['guid'], $seenGuids, true);

            return $item;
        }, $items);
    }
}
