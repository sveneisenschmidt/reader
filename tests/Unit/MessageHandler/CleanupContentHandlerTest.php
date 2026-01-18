<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\MessageHandler;

use App\Domain\Feed\Repository\FeedItemRepository;
use App\Message\CleanupContentMessage;
use App\MessageHandler\CleanupContentHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CleanupContentHandlerTest extends TestCase
{
    #[Test]
    public function trimsItemsPerSubscription(): void
    {
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->expects($this->once())
            ->method('trimToLimitPerSubscription')
            ->with(50)
            ->willReturn(10);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('info');

        $handler = new CleanupContentHandler($feedItemRepository, $logger);
        $handler(new CleanupContentMessage(maxItemsPerSubscription: 50));
    }

    #[Test]
    public function respectsCustomLimit(): void
    {
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->expects($this->once())
            ->method('trimToLimitPerSubscription')
            ->with(100)
            ->willReturn(5);

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new CleanupContentHandler($feedItemRepository, $logger);
        $handler(new CleanupContentMessage(maxItemsPerSubscription: 100));
    }

    #[Test]
    public function logsCleanupDetails(): void
    {
        $feedItemRepository = $this->createMock(FeedItemRepository::class);
        $feedItemRepository
            ->method('trimToLimitPerSubscription')
            ->willReturn(15);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    $this->assertEquals('Cleaning up old content', $message);
                    $this->assertArrayHasKey(
                        'max_items_per_subscription',
                        $context,
                    );
                }

                if ($callCount === 2) {
                    $this->assertEquals('Cleanup completed', $message);
                    $this->assertEquals(['deleted_content' => 15], $context);
                }
            });

        $handler = new CleanupContentHandler($feedItemRepository, $logger);
        $handler(new CleanupContentMessage(maxItemsPerSubscription: 50));
    }
}
