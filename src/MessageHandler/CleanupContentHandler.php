<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\MessageHandler;

use App\Domain\Feed\Repository\FeedItemRepository;
use App\Message\CleanupContentMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanupContentHandler
{
    public function __construct(
        private FeedItemRepository $feedItemRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CleanupContentMessage $message): void
    {
        $this->logger->info('Cleaning up old content', [
            'max_items_per_subscription' => $message->maxItemsPerSubscription,
        ]);

        $deletedContent = $this->feedItemRepository->trimToLimitPerSubscription(
            $message->maxItemsPerSubscription,
        );

        $this->logger->info('Cleanup completed', [
            'deleted_content' => $deletedContent,
        ]);
    }
}
