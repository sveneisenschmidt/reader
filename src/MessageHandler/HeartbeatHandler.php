<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\MessageHandler;

use App\Message\HeartbeatMessage;
use App\Service\WorkerHeartbeat;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class HeartbeatHandler
{
    public function __construct(private WorkerHeartbeat $heartbeat) {}

    public function __invoke(HeartbeatMessage $message): void
    {
        $this->heartbeat->beat();
    }
}
