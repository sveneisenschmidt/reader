<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\MessageHandler;

use App\Domain\Feed\Repository\SubscriptionRepository;
use App\Domain\Feed\Service\FeedExceptionHandler;
use App\Domain\Feed\Service\FeedPersistenceService;
use App\Domain\Feed\Service\FeedReaderService;
use App\Enum\SubscriptionStatus;
use App\Message\RefreshFeedsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshFeedsHandler
{
    public function __construct(
        private FeedReaderService $feedReaderService,
        private FeedPersistenceService $persistenceService,
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
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

        // Group subscriptions by host to apply rate limiting
        $feedsByHost = [];
        foreach ($subscriptions as $subscription) {
            $host =
                parse_url($subscription->getUrl(), PHP_URL_HOST) ?? 'unknown';
            $feedsByHost[$host][] = $subscription;
        }

        $successCount = 0;
        $skippedCount = 0;
        $lastRequestByHost = [];
        $minDelayMs = 1000; // 1 second delay between requests to same host

        foreach ($feedsByHost as $host => $hostSubscriptions) {
            foreach ($hostSubscriptions as $subscription) {
                // Rate limit: wait if needed before requesting same host
                if (isset($lastRequestByHost[$host])) {
                    $elapsedMs =
                        (microtime(true) - $lastRequestByHost[$host]) * 1000;
                    if ($elapsedMs < $minDelayMs) {
                        usleep((int) (($minDelayMs - $elapsedMs) * 1000));
                    }
                }
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

                $startTime = microtime(true);
                try {
                    $this->feedReaderService->fetchAndPersistFeed(
                        $subscription->getUrl(),
                    );
                    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                    $subscription->updateLastRefreshedAt();
                    $subscription->setLastRefreshDuration($durationMs);
                    $subscription->setStatus(SubscriptionStatus::Success);
                    ++$successCount;
                } catch (\Exception $e) {
                    $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                    $subscription->setLastRefreshDuration($durationMs);
                    $status = $this->exceptionHandler->handleException(
                        $e,
                        $subscription,
                    );
                    $subscription->setStatus($status);
                } finally {
                    $lock->release();
                }

                $lastRequestByHost[$host] = microtime(true);
                $this->entityManager->flush();
            }
        }

        // Remove duplicates after all feeds have been imported
        $duplicatesRemoved = $this->persistenceService->deleteDuplicates();

        $this->logger->info('Feeds refreshed', [
            'success' => $successCount,
            'skipped' => $skippedCount,
            'failed' => count($subscriptions) - $successCount - $skippedCount,
            'duplicates_removed' => $duplicatesRemoved,
        ]);
    }
}
