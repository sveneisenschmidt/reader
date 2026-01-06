<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\MessageHandler;

use App\Message\RefreshFeedsMessage;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Service\FeedFetcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshFeedsHandler
{
    public function __construct(
        private FeedFetcher $feedFetcher,
        private SubscriptionRepository $subscriptionRepository,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RefreshFeedsMessage $message): void
    {
        $subscriptions = $this->subscriptionRepository->findAll();
        $urls = array_map(fn($s) => $s->getUrl(), $subscriptions);

        $this->logger->info('Refreshing feeds', ['count' => count($urls)]);

        $this->feedFetcher->refreshAllFeeds($urls);

        $this->logger->info('Feeds refreshed successfully');
    }
}
