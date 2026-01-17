<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Repository\BookmarkStatusRepository;
use PhpStaticAnalysis\Attributes\Returns;

class BookmarkService
{
    public function __construct(
        private BookmarkStatusRepository $bookmarkStatusRepository,
    ) {
    }

    public function bookmark(int $userId, string $feedItemGuid): void
    {
        $this->bookmarkStatusRepository->bookmark($userId, $feedItemGuid);
    }

    public function unbookmark(int $userId, string $feedItemGuid): void
    {
        $this->bookmarkStatusRepository->unbookmark($userId, $feedItemGuid);
    }

    public function isBookmarked(int $userId, string $feedItemGuid): bool
    {
        return $this->bookmarkStatusRepository->isBookmarked($userId, $feedItemGuid);
    }

    #[Returns('list<string>')]
    public function getBookmarkedGuidsForUser(int $userId): array
    {
        return $this->bookmarkStatusRepository->getBookmarkedGuidsForUser($userId);
    }

    public function countByUser(int $userId): int
    {
        return $this->bookmarkStatusRepository->countByUser($userId);
    }
}
