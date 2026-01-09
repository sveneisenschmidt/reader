<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Logs\LogEntry;
use App\Repository\Logs\LogEntryRepository;
use App\Service\StatusIndicator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatusIndicatorTest extends TestCase
{
    #[Test]
    public function isWorkerAliveReturnsTrueWhenHeartbeatIsRecent(): void
    {
        $logEntry = $this->createMock(LogEntry::class);
        $logEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn($logEntry);

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isWorkerAlive());
    }

    #[Test]
    public function isWorkerAliveReturnsFalseWhenHeartbeatIsTooOld(): void
    {
        $logEntry = $this->createMock(LogEntry::class);
        $logEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-30 seconds'));

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn($logEntry);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWorkerAlive());
    }

    #[Test]
    public function isWorkerAliveReturnsFalseWhenNoHeartbeatExists(): void
    {
        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWorkerAlive());
    }

    #[Test]
    public function getWorkerLastBeatReturnsTimestampWhenExists(): void
    {
        $timestamp = new \DateTimeImmutable();
        $logEntry = $this->createMock(LogEntry::class);
        $logEntry->method('getCreatedAt')->willReturn($timestamp);

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn($logEntry);

        $service = new StatusIndicator($repository);

        $this->assertSame($timestamp, $service->getWorkerLastBeat());
    }

    #[Test]
    public function getWorkerLastBeatReturnsNullWhenNoEntry(): void
    {
        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertNull($service->getWorkerLastBeat());
    }

    #[Test]
    public function isWebhookAliveReturnsTrueWhenRecentAndSuccessful(): void
    {
        $logEntry = $this->createMock(LogEntry::class);
        $logEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $logEntry->method('getStatus')->willReturn(LogEntry::STATUS_SUCCESS);

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn($logEntry);

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenTooOld(): void
    {
        $logEntry = $this->createMock(LogEntry::class);
        $logEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable('-10 minutes'));
        $logEntry->method('getStatus')->willReturn(LogEntry::STATUS_SUCCESS);

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn($logEntry);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenStatusIsError(): void
    {
        $logEntry = $this->createMock(LogEntry::class);
        $logEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $logEntry->method('getStatus')->willReturn(LogEntry::STATUS_ERROR);

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn($logEntry);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenNoWebhookExists(): void
    {
        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWebhookAlive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenWorkerIsAlive(): void
    {
        $workerEntry = $this->createMock(LogEntry::class);
        $workerEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn($workerEntry);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isActive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenWebhookIsAlive(): void
    {
        $webhookEntry = $this->createMock(LogEntry::class);
        $webhookEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $webhookEntry->method('getStatus')->willReturn(LogEntry::STATUS_SUCCESS);

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn(null);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn($webhookEntry);

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isActive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenBothAreAlive(): void
    {
        $workerEntry = $this->createMock(LogEntry::class);
        $workerEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());

        $webhookEntry = $this->createMock(LogEntry::class);
        $webhookEntry->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $webhookEntry->method('getStatus')->willReturn(LogEntry::STATUS_SUCCESS);

        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn($workerEntry);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn($webhookEntry);

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isActive());
    }

    #[Test]
    public function isActiveReturnsFalseWhenNeitherIsAlive(): void
    {
        $repository = $this->createMock(LogEntryRepository::class);
        $repository->method('getLastByChannelAndAction')
            ->with(LogEntry::CHANNEL_WORKER, 'heartbeat')
            ->willReturn(null);
        $repository->method('getLastByChannel')
            ->with(LogEntry::CHANNEL_WEBHOOK)
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isActive());
    }
}
