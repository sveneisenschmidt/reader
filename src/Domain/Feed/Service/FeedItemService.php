<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\Feed\Service;

use App\Domain\Feed\Entity\FeedItem;
use App\Domain\Feed\Repository\FeedItemRepository;

class FeedItemService
{
    public function __construct(
        private FeedItemRepository $feedItemRepository,
    ) {
    }

    public function findByGuid(string $guid): ?FeedItem
    {
        return $this->feedItemRepository->findByGuid($guid);
    }
}
