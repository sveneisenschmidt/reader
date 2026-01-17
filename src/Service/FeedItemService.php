<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\FeedItem;
use App\Repository\FeedItemRepository;

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
