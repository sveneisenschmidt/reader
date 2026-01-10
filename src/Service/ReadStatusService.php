<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Repository\Users\ReadStatusRepository;

class ReadStatusService
{
    public function __construct(
        private ReadStatusRepository $readStatusRepository,
    ) {
    }

    public function markAsRead(int $userId, string $feedItemGuid): void
    {
        $this->readStatusRepository->markAsRead($userId, $feedItemGuid);
    }

    public function markAsUnread(int $userId, string $feedItemGuid): void
    {
        $this->readStatusRepository->markAsUnread($userId, $feedItemGuid);
    }

    public function markManyAsRead(int $userId, array $feedItemGuids): void
    {
        $this->readStatusRepository->markManyAsRead($userId, $feedItemGuids);
    }

    public function isRead(int $userId, string $feedItemGuid): bool
    {
        return $this->readStatusRepository->isRead($userId, $feedItemGuid);
    }

    public function getReadGuidsForUser(int $userId): array
    {
        return $this->readStatusRepository->getReadGuidsForUser($userId);
    }

    public function enrichItemsWithReadStatus(array $items, int $userId): array
    {
        $readGuids = $this->getReadGuidsForUser($userId);

        return array_map(function ($item) use ($readGuids) {
            $item['isRead'] = in_array($item['guid'], $readGuids, true);

            return $item;
        }, $items);
    }
}
