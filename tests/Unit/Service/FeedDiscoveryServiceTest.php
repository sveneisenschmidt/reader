<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\Discovery\FeedDiscoveryService;
use FeedIo\Feed;
use FeedIo\FeedIo;
use FeedIo\Reader\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeedDiscoveryServiceTest extends TestCase
{
    #[Test]
    public function normalizeUrlAddsHttpsIfMissing(): void
    {
        $service = $this->createService();

        $this->assertEquals('https://example.com', $service->normalizeUrl('example.com'));
        $this->assertEquals('https://example.com/feed', $service->normalizeUrl('example.com/feed'));
    }

    #[Test]
    public function normalizeUrlPreservesExistingProtocol(): void
    {
        $service = $this->createService();

        $this->assertEquals('https://example.com', $service->normalizeUrl('https://example.com'));
        $this->assertEquals('http://example.com', $service->normalizeUrl('http://example.com'));
    }

    #[Test]
    public function normalizeUrlTrimsWhitespace(): void
    {
        $service = $this->createService();

        $this->assertEquals('https://example.com', $service->normalizeUrl('  example.com  '));
    }

    #[Test]
    public function resolveToFeedUrlReturnsDirectFeedUrl(): void
    {
        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->expects($this->once())
            ->method('read')
            ->with('https://example.com/feed.xml')
            ->willReturn($this->createMock(Result::class));

        $service = $this->createService($feedIo);

        $result = $service->resolveToFeedUrl('https://example.com/feed.xml');

        $this->assertEquals('https://example.com/feed.xml', $result['feedUrl']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function resolveToFeedUrlDiscoversFromWebpage(): void
    {
        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->expects($this->once())
            ->method('read')
            ->willThrowException(new \Exception('Not a feed'));
        $feedIo
            ->expects($this->once())
            ->method('discover')
            ->with('https://example.com')
            ->willReturn(['https://example.com/feed.xml', 'https://example.com/atom.xml']);

        $service = $this->createService($feedIo);

        $result = $service->resolveToFeedUrl('https://example.com');

        $this->assertEquals('https://example.com/feed.xml', $result['feedUrl']);
        $this->assertNull($result['error']);
    }

    #[Test]
    public function resolveToFeedUrlReturnsErrorWhenNoFeedsFound(): void
    {
        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->method('read')
            ->willThrowException(new \Exception('Not a feed'));
        $feedIo
            ->method('discover')
            ->willReturn([]);

        $service = $this->createService($feedIo);

        $result = $service->resolveToFeedUrl('https://example.com');

        $this->assertNull($result['feedUrl']);
        $this->assertEquals('No RSS or Atom feed found on this website', $result['error']);
    }

    #[Test]
    public function resolveToFeedUrlReturnsErrorOnDiscoveryFailure(): void
    {
        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->method('read')
            ->willThrowException(new \Exception('Not a feed'));
        $feedIo
            ->method('discover')
            ->willThrowException(new \Exception('Connection failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Feed discovery failed', $this->anything());

        $service = $this->createService($feedIo, $logger);

        $result = $service->resolveToFeedUrl('https://example.com');

        $this->assertNull($result['feedUrl']);
        $this->assertStringContainsString('Could not fetch URL', $result['error']);
    }

    #[Test]
    public function validateFeedUrlReturnsNullForValidFeed(): void
    {
        $feed = $this->createMock(Feed::class);
        $feed->method('count')->willReturn(5);

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->expects($this->once())
            ->method('read')
            ->willReturn($result);

        $service = $this->createService($feedIo);

        $error = $service->validateFeedUrl('https://example.com/feed.xml');

        $this->assertNull($error);
    }

    #[Test]
    public function validateFeedUrlReturnsErrorForEmptyFeed(): void
    {
        $feed = $this->createMock(Feed::class);
        $feed->method('count')->willReturn(0);

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $service = $this->createService($feedIo);

        $error = $service->validateFeedUrl('https://example.com/feed.xml');

        $this->assertEquals('URL is not a valid RSS or Atom feed', $error);
    }

    #[Test]
    public function validateFeedUrlReturnsErrorOnException(): void
    {
        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->method('read')
            ->willThrowException(new \Exception('Connection failed'));

        $service = $this->createService($feedIo);

        $error = $service->validateFeedUrl('https://example.com/feed.xml');

        $this->assertStringContainsString('Could not fetch URL', $error);
    }

    private function createService(
        ?FeedIo $feedIo = null,
        ?LoggerInterface $logger = null,
    ): FeedDiscoveryService {
        return new FeedDiscoveryService(
            $feedIo ?? $this->createStub(FeedIo::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
