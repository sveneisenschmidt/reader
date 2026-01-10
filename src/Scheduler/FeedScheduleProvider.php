<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Scheduler;

use App\Message\CleanupContentMessage;
use App\Message\HeartbeatMessage;
use App\Message\RefreshFeedsMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

#[CodeCoverageIgnore]
#[AsSchedule("default")]
class FeedScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        #[
            Autowire(env: "WORKER_REFRESH_INTERVAL"),
        ]
        private string $refreshInterval,
        #[
            Autowire(env: "WORKER_CLEANUP_INTERVAL"),
        ]
        private string $cleanupInterval,
    ) {}

    public function getSchedule(): Schedule
    {
        return new Schedule()
            ->add(RecurringMessage::every("10 seconds", new HeartbeatMessage()))
            ->add(
                RecurringMessage::every(
                    $this->refreshInterval,
                    new RefreshFeedsMessage(),
                ),
            )
            ->add(
                RecurringMessage::every(
                    $this->cleanupInterval,
                    new CleanupContentMessage(olderThanDays: 30),
                ),
            );
    }
}
