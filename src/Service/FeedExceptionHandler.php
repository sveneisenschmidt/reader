<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Subscriptions\Subscription;
use App\Enum\SubscriptionStatus;
use FeedIo\Adapter\HttpRequestException;
use FeedIo\Parser\MissingFieldsException;
use FeedIo\Parser\UnsupportedFormatException;
use FeedIo\Reader\NoAccurateParserException;
use Psr\Log\LoggerInterface;

class FeedExceptionHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function handleException(
        \Throwable $e,
        Subscription $subscription,
    ): SubscriptionStatus {
        $status = $this->determineStatus($e);

        $this->logger->warning('Failed to refresh feed', [
            'url' => $subscription->getUrl(),
            'status' => $status->value,
            'error' => $e->getMessage(),
        ]);

        return $status;
    }

    private function determineStatus(\Throwable $e): SubscriptionStatus
    {
        return match (true) {
            $e instanceof HttpRequestException => str_contains(
                $e->getMessage(),
                'timed out',
            )
                ? SubscriptionStatus::Timeout
                : SubscriptionStatus::Unreachable,
            $e instanceof NoAccurateParserException,
            $e instanceof UnsupportedFormatException,
            $e instanceof MissingFieldsException => SubscriptionStatus::Invalid,
            default => SubscriptionStatus::Unreachable,
        };
    }
}
