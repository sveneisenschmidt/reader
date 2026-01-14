<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\MessageHandler;

use App\Message\CleanupContentMessage;
use App\MessageHandler\CleanupContentHandler;
use App\Repository\FeedItemRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupContentHandlerTest extends TestCase
{
    #[Test]
    public function deletesOldContent(): void
    {
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeInterface $date) {
                $expected = new \DateTimeImmutable('-30 days');

                return abs($date->getTimestamp() - $expected->getTimestamp()) < 5;
            }))
            ->willReturn(10);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('info');

        $handler = new CleanupContentHandler($feedItemRepository, $logger);
        $handler(new CleanupContentMessage(olderThanDays: 30));
    }

    #[Test]
    public function respectsCustomOlderThanDays(): void
    {
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeInterface $date) {
                $expected = new \DateTimeImmutable('-7 days');

                return abs($date->getTimestamp() - $expected->getTimestamp()) < 5;
            }))
            ->willReturn(5);

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new CleanupContentHandler($feedItemRepository, $logger);
        $handler(new CleanupContentMessage(olderThanDays: 7));
    }

    #[Test]
    public function logsCleanupDetails(): void
    {
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository->method('deleteOlderThan')->willReturn(15);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    $this->assertEquals('Cleaning up old content', $message);
                    $this->assertArrayHasKey('older_than_days', $context);
                    $this->assertArrayHasKey('cutoff_date', $context);
                }

                if ($callCount === 2) {
                    $this->assertEquals('Cleanup completed', $message);
                    $this->assertEquals(['deleted_content' => 15], $context);
                }
            });

        $handler = new CleanupContentHandler($feedItemRepository, $logger);
        $handler(new CleanupContentMessage(olderThanDays: 30));
    }
}
