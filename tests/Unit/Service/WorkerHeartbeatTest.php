<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Messages\ProcessedMessage;
use App\Message\HeartbeatMessage;
use App\Repository\Messages\ProcessedMessageRepository;
use App\Service\WorkerHeartbeat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkerHeartbeatTest extends TestCase
{
    #[Test]
    public function getLastBeatReturnsTimestampWhenExists(): void
    {
        $timestamp = new \DateTimeImmutable();
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage->method('getProcessedAt')->willReturn($timestamp);

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new WorkerHeartbeat($repository);

        $this->assertSame($timestamp, $service->getLastBeat());
    }

    #[Test]
    public function getLastBeatReturnsNullWhenNoEntry(): void
    {
        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn(null);

        $service = new WorkerHeartbeat($repository);

        $this->assertNull($service->getLastBeat());
    }

    #[Test]
    public function isAliveReturnsTrueWhenHeartbeatIsRecent(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage->method('getProcessedAt')->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new WorkerHeartbeat($repository);

        $this->assertTrue($service->isAlive());
    }

    #[Test]
    public function isAliveReturnsFalseWhenHeartbeatIsTooOld(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage->method('getProcessedAt')->willReturn(new \DateTimeImmutable('-60 seconds'));

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new WorkerHeartbeat($repository);

        $this->assertFalse($service->isAlive());
    }

    #[Test]
    public function isAliveReturnsFalseWhenNoHeartbeatExists(): void
    {
        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn(null);

        $service = new WorkerHeartbeat($repository);

        $this->assertFalse($service->isAlive());
    }

    #[Test]
    public function isAliveRespectsCustomMaxAge(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage->method('getProcessedAt')->willReturn(new \DateTimeImmutable('-45 seconds'));

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new WorkerHeartbeat($repository);

        $this->assertFalse($service->isAlive(30));
        $this->assertTrue($service->isAlive(60));
    }
}
