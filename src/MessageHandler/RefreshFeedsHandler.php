<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\MessageHandler;

use App\Enum\SubscriptionStatus;
use App\Message\RefreshFeedsMessage;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Service\FeedExceptionHandler;
use App\Service\FeedReaderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshFeedsHandler
{
    public function __construct(
        private FeedReaderService $feedReaderService,
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $subscriptionsEntityManager,
        private LoggerInterface $logger,
        private FeedExceptionHandler $exceptionHandler,
        private LockFactory $lockFactory,
    ) {
    }

    public function __invoke(RefreshFeedsMessage $message): void
    {
        $subscriptions = $this->subscriptionRepository->findAll();

        $this->logger->info('Refreshing feeds', [
            'count' => count($subscriptions),
        ]);

        $successCount = 0;
        $skippedCount = 0;
        foreach ($subscriptions as $subscription) {
            $lock = $this->lockFactory->createLock(
                'feed-refresh-'.$subscription->getId(),
                ttl: 300,
            );

            if (!$lock->acquire(blocking: false)) {
                $this->logger->info(
                    'Feed refresh already in progress, skipping',
                    [
                        'subscription_id' => $subscription->getId(),
                        'url' => $subscription->getUrl(),
                    ],
                );
                ++$skippedCount;
                continue;
            }

            try {
                $this->feedReaderService->fetchAndPersistFeed(
                    $subscription->getUrl(),
                );
                $subscription->updateLastRefreshedAt();
                $subscription->setStatus(SubscriptionStatus::Success);
                ++$successCount;
            } catch (\Exception $e) {
                $status = $this->exceptionHandler->handleException(
                    $e,
                    $subscription,
                );
                $subscription->setStatus($status);
            } finally {
                $lock->release();
            }
            $this->subscriptionsEntityManager->flush();
        }

        $this->logger->info('Feeds refreshed', [
            'success' => $successCount,
            'skipped' => $skippedCount,
            'failed' => count($subscriptions) - $successCount - $skippedCount,
        ]);
    }
}
