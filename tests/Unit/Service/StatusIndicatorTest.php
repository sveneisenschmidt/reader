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
use App\Enum\MessageSource;
use App\Message\HeartbeatMessage;
use App\Message\RefreshFeedsMessage;
use App\Repository\Messages\ProcessedMessageRepository;
use App\Service\StatusIndicator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatusIndicatorTest extends TestCase
{
    #[Test]
    public function isWorkerAliveReturnsTrueWhenHeartbeatIsRecent(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage
            ->method('getProcessedAt')
            ->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isWorkerAlive());
    }

    #[Test]
    public function isWorkerAliveReturnsFalseWhenHeartbeatIsTooOld(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage
            ->method('getProcessedAt')
            ->willReturn(new \DateTimeImmutable('-30 seconds'));

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWorkerAlive());
    }

    #[Test]
    public function isWorkerAliveReturnsFalseWhenNoHeartbeatExists(): void
    {
        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWorkerAlive());
    }

    #[Test]
    public function getWorkerLastBeatReturnsTimestampWhenExists(): void
    {
        $timestamp = new \DateTimeImmutable();
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage->method('getProcessedAt')->willReturn($timestamp);

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($processedMessage);

        $service = new StatusIndicator($repository);

        $this->assertSame($timestamp, $service->getWorkerLastBeat());
    }

    #[Test]
    public function getWorkerLastBeatReturnsNullWhenNoEntry(): void
    {
        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertNull($service->getWorkerLastBeat());
    }

    #[Test]
    public function isWebhookAliveReturnsTrueWhenRecentRefreshFeedsExists(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage
            ->method('getProcessedAt')
            ->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function (
                string $type,
                MessageSource $source,
            ) use ($processedMessage) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $processedMessage;
                }

                return null;
            });

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenTooOld(): void
    {
        $processedMessage = $this->createMock(ProcessedMessage::class);
        $processedMessage
            ->method('getProcessedAt')
            ->willReturn(new \DateTimeImmutable('-10 minutes'));

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function (
                string $type,
                MessageSource $source,
            ) use ($processedMessage) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $processedMessage;
                }

                return null;
            });

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenNoWebhookExists(): void
    {
        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByTypeAndSource')->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isWebhookAlive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenWorkerIsAlive(): void
    {
        $workerMessage = $this->createMock(ProcessedMessage::class);
        $workerMessage
            ->method('getProcessedAt')
            ->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByType')
            ->willReturnCallback(function (string $type) use ($workerMessage) {
                if ($type === HeartbeatMessage::class) {
                    return $workerMessage;
                }

                return null;
            });

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isActive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenWebhookIsAlive(): void
    {
        $webhookMessage = $this->createMock(ProcessedMessage::class);
        $webhookMessage
            ->method('getProcessedAt')
            ->willReturn(new \DateTimeImmutable());

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function (
                string $type,
                MessageSource $source,
            ) use ($webhookMessage) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $webhookMessage;
                }

                return null;
            });

        $service = new StatusIndicator($repository);

        $this->assertTrue($service->isActive());
    }

    #[Test]
    public function isActiveReturnsFalseWhenNeitherIsAlive(): void
    {
        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->method('getLastSuccessByType')->willReturn(null);

        $service = new StatusIndicator($repository);

        $this->assertFalse($service->isActive());
    }
}
