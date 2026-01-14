<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Entity\ProcessedMessage;
use App\Enum\MessageSource;
use App\Message\CleanupContentMessage;
use App\Message\HeartbeatMessage;
use App\Message\RefreshFeedsMessage;
use App\Repository\ProcessedMessageRepository;
use App\Service\StatusIndicator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatusIndicatorTest extends TestCase
{
    private ProcessedMessageRepository&MockObject $repository;
    private StatusIndicator $statusIndicator;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(
            ProcessedMessageRepository::class,
        );
        $this->statusIndicator = new StatusIndicator($this->repository);
    }

    private function createProcessedMessage(
        \DateTimeImmutable $processedAt,
    ): ProcessedMessage {
        $message = new ProcessedMessage(
            HeartbeatMessage::class,
            ProcessedMessage::STATUS_SUCCESS,
        );
        // Use reflection to set the processedAt since it's set in constructor
        $reflection = new \ReflectionClass($message);
        $property = $reflection->getProperty('processedAt');
        $property->setValue($message, $processedAt);

        return $message;
    }

    #[Test]
    public function isWorkerAliveReturnsTrueWhenRecentHeartbeat(): void
    {
        $recentTime = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($recentTime);

        $this->repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($message);

        $this->assertTrue($this->statusIndicator->isWorkerAlive());
    }

    #[Test]
    public function isWorkerAliveReturnsFalseWhenOldHeartbeat(): void
    {
        $oldTime = new \DateTimeImmutable('-30 seconds');
        $message = $this->createProcessedMessage($oldTime);

        $this->repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($message);

        $this->assertFalse($this->statusIndicator->isWorkerAlive());
    }

    #[Test]
    public function isWorkerAliveReturnsFalseWhenNoHeartbeat(): void
    {
        $this->repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn(null);

        $this->assertFalse($this->statusIndicator->isWorkerAlive());
    }

    #[Test]
    public function getWorkerLastBeatReturnsDateWhenHeartbeatExists(): void
    {
        $time = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($time);

        $this->repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn($message);

        $this->assertEquals($time, $this->statusIndicator->getWorkerLastBeat());
    }

    #[Test]
    public function getWorkerLastBeatReturnsNullWhenNoHeartbeat(): void
    {
        $this->repository
            ->method('getLastSuccessByType')
            ->with(HeartbeatMessage::class)
            ->willReturn(null);

        $this->assertNull($this->statusIndicator->getWorkerLastBeat());
    }

    #[Test]
    public function isWebhookAliveReturnsTrueWhenRecentRefresh(): void
    {
        $recentTime = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($recentTime);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use ($message) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $message;
                }

                return null;
            });

        $this->assertTrue($this->statusIndicator->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsTrueWhenRecentCleanup(): void
    {
        $recentTime = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($recentTime);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use ($message) {
                if (
                    $type === CleanupContentMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $message;
                }

                return null;
            });

        $this->assertTrue($this->statusIndicator->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenOldActivity(): void
    {
        $oldTime = new \DateTimeImmutable('-10 minutes');
        $message = $this->createProcessedMessage($oldTime);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use ($message) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $message;
                }

                return null;
            });

        $this->assertFalse($this->statusIndicator->isWebhookAlive());
    }

    #[Test]
    public function isWebhookAliveReturnsFalseWhenNoActivity(): void
    {
        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturn(null);

        $this->assertFalse($this->statusIndicator->isWebhookAlive());
    }

    #[Test]
    public function getWebhookLastBeatReturnsLatestOfRefreshAndCleanup(): void
    {
        $olderTime = new \DateTimeImmutable('-2 minutes');
        $newerTime = new \DateTimeImmutable('-1 minute');
        $olderMessage = $this->createProcessedMessage($olderTime);
        $newerMessage = $this->createProcessedMessage($newerTime);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use (
                $olderMessage,
                $newerMessage,
            ) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $olderMessage;
                }
                if (
                    $type === CleanupContentMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $newerMessage;
                }

                return null;
            });

        $this->assertEquals(
            $newerTime,
            $this->statusIndicator->getWebhookLastBeat(),
        );
    }

    #[Test]
    public function getWebhookLastBeatReturnsRefreshWhenOnlyRefreshExists(): void
    {
        $time = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($time);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use ($message) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $message;
                }

                return null;
            });

        $this->assertEquals(
            $time,
            $this->statusIndicator->getWebhookLastBeat(),
        );
    }

    #[Test]
    public function getWebhookLastBeatReturnsCleanupWhenOnlyCleanupExists(): void
    {
        $time = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($time);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use ($message) {
                if (
                    $type === CleanupContentMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $message;
                }

                return null;
            });

        $this->assertEquals(
            $time,
            $this->statusIndicator->getWebhookLastBeat(),
        );
    }

    #[Test]
    public function getWebhookLastBeatReturnsNullWhenNoActivity(): void
    {
        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturn(null);

        $this->assertNull($this->statusIndicator->getWebhookLastBeat());
    }

    #[Test]
    public function isActiveReturnsTrueWhenWorkerAlive(): void
    {
        $recentTime = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($recentTime);

        $this->repository
            ->method('getLastSuccessByType')
            ->willReturnCallback(function ($type) use ($message) {
                if ($type === HeartbeatMessage::class) {
                    return $message;
                }

                return null;
            });

        $this->assertTrue($this->statusIndicator->isActive());
    }

    #[Test]
    public function isActiveReturnsTrueWhenWebhookAlive(): void
    {
        $recentTime = new \DateTimeImmutable('now');
        $message = $this->createProcessedMessage($recentTime);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use ($message) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $message;
                }

                return null;
            });

        $this->assertTrue($this->statusIndicator->isActive());
    }

    #[Test]
    public function isActiveReturnsFalseWhenNothingAlive(): void
    {
        $this->repository->method('getLastSuccessByType')->willReturn(null);
        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturn(null);

        $this->assertFalse($this->statusIndicator->isActive());
    }

    #[Test]
    public function getWebhookLastBeatReturnsRefreshWhenRefreshIsNewer(): void
    {
        $olderTime = new \DateTimeImmutable('-2 minutes');
        $newerTime = new \DateTimeImmutable('-1 minute');
        $olderMessage = $this->createProcessedMessage($olderTime);
        $newerMessage = $this->createProcessedMessage($newerTime);

        $this->repository
            ->method('getLastSuccessByTypeAndSource')
            ->willReturnCallback(function ($type, $source) use (
                $olderMessage,
                $newerMessage,
            ) {
                if (
                    $type === RefreshFeedsMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $newerMessage; // refresh is newer
                }
                if (
                    $type === CleanupContentMessage::class
                    && $source === MessageSource::Webhook
                ) {
                    return $olderMessage;
                }

                return null;
            });

        $this->assertEquals(
            $newerTime,
            $this->statusIndicator->getWebhookLastBeat(),
        );
    }
}
