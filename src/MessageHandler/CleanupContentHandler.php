<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\MessageHandler;

use App\Message\CleanupContentMessage;
use App\Repository\FeedItemRepository;
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
        $cutoffDate = new \DateTimeImmutable("-{$message->olderThanDays} days");

        $this->logger->info('Cleaning up old content', [
            'older_than_days' => $message->olderThanDays,
            'cutoff_date' => $cutoffDate->format('Y-m-d'),
        ]);

        $deletedContent = $this->feedItemRepository->deleteOlderThan(
            $cutoffDate,
        );

        $this->logger->info('Cleanup completed', [
            'deleted_content' => $deletedContent,
        ]);
    }
}
