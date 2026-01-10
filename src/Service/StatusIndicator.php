<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Enum\MessageSource;
use App\Message\CleanupContentMessage;
use App\Message\HeartbeatMessage;
use App\Message\RefreshFeedsMessage;
use App\Repository\Messages\ProcessedMessageRepository;

class StatusIndicator
{
    private const WORKER_MAX_AGE = 15;
    private const WEBHOOK_MAX_AGE = 300;

    public function __construct(
        private ProcessedMessageRepository $processedMessageRepository,
    ) {
    }

    public function isWorkerAlive(): bool
    {
        $lastBeat = $this->getWorkerLastBeat();

        if ($lastBeat === null) {
            return false;
        }

        return time() - $lastBeat->getTimestamp() <= self::WORKER_MAX_AGE;
    }

    public function getWorkerLastBeat(): ?\DateTimeImmutable
    {
        $entry = $this->processedMessageRepository->getLastSuccessByType(
            HeartbeatMessage::class,
        );

        return $entry?->getProcessedAt();
    }

    public function isWebhookAlive(): bool
    {
        $lastBeat = $this->getWebhookLastBeat();

        if ($lastBeat === null) {
            return false;
        }

        return time() - $lastBeat->getTimestamp() <= self::WEBHOOK_MAX_AGE;
    }

    public function getWebhookLastBeat(): ?\DateTimeImmutable
    {
        $lastRefresh = $this->processedMessageRepository->getLastSuccessByTypeAndSource(
            RefreshFeedsMessage::class,
            MessageSource::Webhook,
        );
        $lastCleanup = $this->processedMessageRepository->getLastSuccessByTypeAndSource(
            CleanupContentMessage::class,
            MessageSource::Webhook,
        );

        if ($lastRefresh !== null && $lastCleanup !== null) {
            return $lastRefresh->getProcessedAt() >
                $lastCleanup->getProcessedAt()
                ? $lastRefresh->getProcessedAt()
                : $lastCleanup->getProcessedAt();
        } elseif ($lastRefresh !== null) {
            return $lastRefresh->getProcessedAt();
        } elseif ($lastCleanup !== null) {
            return $lastCleanup->getProcessedAt();
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->isWorkerAlive() || $this->isWebhookAlive();
    }
}
