<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Logs\LogEntry;
use App\Repository\Logs\LogEntryRepository;

class StatusIndicator
{
    private const WORKER_MAX_AGE = 15;
    private const WEBHOOK_MAX_AGE = 300;

    public function __construct(
        private LogEntryRepository $logEntryRepository,
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
        $entry = $this->logEntryRepository->getLastByChannelAndAction(
            LogEntry::CHANNEL_WORKER,
            'heartbeat',
        );

        return $entry?->getCreatedAt();
    }

    public function isWebhookAlive(): bool
    {
        $lastWebhook = $this->logEntryRepository->getLastByChannel(
            LogEntry::CHANNEL_WEBHOOK,
        );

        if ($lastWebhook === null) {
            return false;
        }

        if ($lastWebhook->getStatus() !== LogEntry::STATUS_SUCCESS) {
            return false;
        }

        return $lastWebhook->getCreatedAt()->getTimestamp() > time() - self::WEBHOOK_MAX_AGE;
    }

    public function isActive(): bool
    {
        return $this->isWorkerAlive() || $this->isWebhookAlive();
    }
}
