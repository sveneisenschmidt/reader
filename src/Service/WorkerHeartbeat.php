<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Message\HeartbeatMessage;
use App\Repository\ProcessedMessageRepository;

class WorkerHeartbeat
{
    public function __construct(
        private ProcessedMessageRepository $processedMessageRepository,
    ) {
    }

    public function getLastBeat(): ?\DateTimeImmutable
    {
        $entry = $this->processedMessageRepository->getLastSuccessByType(
            HeartbeatMessage::class,
        );

        return $entry?->getProcessedAt();
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
