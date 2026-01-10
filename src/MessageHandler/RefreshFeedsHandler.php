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
use App\Service\FeedReaderService;
use Doctrine\ORM\EntityManagerInterface;
use FeedIo\Adapter\HttpRequestException;
use FeedIo\Adapter\NotFoundException;
use FeedIo\Adapter\ServerErrorException;
use FeedIo\Parser\MissingFieldsException;
use FeedIo\Parser\UnsupportedFormatException;
use FeedIo\Reader\NoAccurateParserException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RefreshFeedsHandler
{
    public function __construct(
        private FeedReaderService $feedReaderService,
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $subscriptionsEntityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RefreshFeedsMessage $message): void
    {
        $subscriptions = $this->subscriptionRepository->findAll();

        $this->logger->info('Refreshing feeds', [
            'count' => count($subscriptions),
        ]);

        $successCount = 0;
        foreach ($subscriptions as $subscription) {
            try {
                $this->feedReaderService->fetchAndPersistFeed(
                    $subscription->getUrl(),
                );
                $subscription->updateLastRefreshedAt();
                $subscription->setStatus(SubscriptionStatus::Success);
                $this->subscriptionsEntityManager->flush();
                ++$successCount;
            } catch (HttpRequestException|NotFoundException|ServerErrorException $e) {
                $status = str_contains($e->getMessage(), 'timed out')
                    ? SubscriptionStatus::Timeout
                    : SubscriptionStatus::Unreachable;
                $subscription->setStatus($status);
                $this->subscriptionsEntityManager->flush();
                $this->logger->error('Failed to refresh feed', [
                    'url' => $subscription->getUrl(),
                    'status' => $status->value,
                    'error' => $e->getMessage(),
                ]);
            } catch (NoAccurateParserException|UnsupportedFormatException|MissingFieldsException $e) {
                $subscription->setStatus(SubscriptionStatus::Invalid);
                $this->subscriptionsEntityManager->flush();
                $this->logger->error('Failed to refresh feed', [
                    'url' => $subscription->getUrl(),
                    'status' => SubscriptionStatus::Invalid->value,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                $subscription->setStatus(SubscriptionStatus::Unreachable);
                $this->subscriptionsEntityManager->flush();
                $this->logger->error('Failed to refresh feed', [
                    'url' => $subscription->getUrl(),
                    'status' => SubscriptionStatus::Unreachable->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Feeds refreshed', [
            'success' => $successCount,
            'failed' => count($subscriptions) - $successCount,
        ]);
    }
}
