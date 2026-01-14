<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Repository\ReadStatusRepository;
use PhpStaticAnalysis\Attributes\Param;

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

    #[Param(feedItemGuids: 'list<string>')]
    public function markManyAsRead(int $userId, array $feedItemGuids): void
    {
        $this->readStatusRepository->markManyAsRead($userId, $feedItemGuids);
    }

    public function isRead(int $userId, string $feedItemGuid): bool
    {
        return $this->readStatusRepository->isRead($userId, $feedItemGuid);
    }
}
