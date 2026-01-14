<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Repository\SeenStatusRepository;
use PhpStaticAnalysis\Attributes\Param;

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

    #[Param(feedItemGuids: 'list<string>')]
    public function markManyAsSeen(int $userId, array $feedItemGuids): void
    {
        $this->seenStatusRepository->markManyAsSeen($userId, $feedItemGuids);
    }

    public function isSeen(int $userId, string $feedItemGuid): bool
    {
        return $this->seenStatusRepository->isSeen($userId, $feedItemGuid);
    }
}
