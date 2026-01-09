<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Messages\ProcessedMessage;
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
    ) {}

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
        $lastRefresh = $this->processedMessageRepository->getLastSuccessByType(
            RefreshFeedsMessage::class,
        );
        $lastCleanup = $this->processedMessageRepository->getLastSuccessByType(
            CleanupContentMessage::class,
        );

        $lastWebhook = null;
        if ($lastRefresh !== null && $lastCleanup !== null) {
            $lastWebhook =
                $lastRefresh->getProcessedAt() > $lastCleanup->getProcessedAt()
                    ? $lastRefresh
                    : $lastCleanup;
        } elseif ($lastRefresh !== null) {
            $lastWebhook = $lastRefresh;
        } elseif ($lastCleanup !== null) {
            $lastWebhook = $lastCleanup;
        }

        if ($lastWebhook === null) {
            return false;
        }

        return $lastWebhook->getProcessedAt()->getTimestamp() >
            time() - self::WEBHOOK_MAX_AGE;
    }

    public function isActive(): bool
    {
        return $this->isWorkerAlive() || $this->isWebhookAlive();
    }
}
