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

class WorkerHeartbeat
{
    public function __construct(
        private LogEntryRepository $logEntryRepository,
    ) {}

    public function beat(): void
    {
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WORKER,
            "heartbeat",
            LogEntry::STATUS_SUCCESS,
        );
    }

    public function getLastBeat(): ?\DateTimeImmutable
    {
        $entry = $this->logEntryRepository->getLastByChannelAndAction(
            LogEntry::CHANNEL_WORKER,
            "heartbeat",
        );

        return $entry?->getCreatedAt();
    }

    public function isAlive(int $maxAge = 30): bool
    {
        $lastBeat = $this->getLastBeat();

        if ($lastBeat === null) {
            return false;
        }

        return time() - $lastBeat->getTimestamp() <= $maxAge;
    }
}
