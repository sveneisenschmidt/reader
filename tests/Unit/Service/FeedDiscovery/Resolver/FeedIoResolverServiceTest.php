<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service\FeedDiscovery\Resolver;

use App\Service\FeedDiscovery\Resolver\FeedIoResolverService;
use App\Service\FeedDiscoveryService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedIoResolverServiceTest extends TestCase
{
    #[Test]
    public function supportsAlwaysReturnsTrue(): void
    {
        $feedDiscoveryService = $this->createMock(FeedDiscoveryService::class);
        $resolver = new FeedIoResolverService($feedDiscoveryService);

        $this->assertTrue($resolver->supports('anything'));
        $this->assertTrue($resolver->supports('https://example.com'));
        $this->assertTrue($resolver->supports(''));
    }

    #[Test]
    public function resolveReturnsSuccessWhenFeedUrlFound(): void
    {
        $feedDiscoveryService = $this->createMock(FeedDiscoveryService::class);
        $feedDiscoveryService
            ->expects($this->once())
            ->method('resolveToFeedUrl')
            ->with('https://example.com')
            ->willReturn([
                'feedUrl' => 'https://example.com/feed.xml',
                'error' => null,
            ]);

        $resolver = new FeedIoResolverService($feedDiscoveryService);

        $result = $resolver->resolve('https://example.com');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('feed-io', $result->getResolverName());
        $this->assertEquals('https://example.com/feed.xml', $result->getFeedUrl());
        $this->assertNull($result->getError());
    }

    #[Test]
    public function resolveReturnsErrorWhenNoFeedFound(): void
    {
        $feedDiscoveryService = $this->createMock(FeedDiscoveryService::class);
        $feedDiscoveryService
            ->expects($this->once())
            ->method('resolveToFeedUrl')
            ->with('https://example.com')
            ->willReturn([
                'feedUrl' => null,
                'error' => 'No RSS or Atom feed found on this website',
            ]);

        $resolver = new FeedIoResolverService($feedDiscoveryService);

        $result = $resolver->resolve('https://example.com');

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('feed-io', $result->getResolverName());
        $this->assertNull($result->getFeedUrl());
        $this->assertEquals('No RSS or Atom feed found on this website', $result->getError());
    }

    #[Test]
    public function resolveReturnsErrorOnConnectionFailure(): void
    {
        $feedDiscoveryService = $this->createMock(FeedDiscoveryService::class);
        $feedDiscoveryService
            ->expects($this->once())
            ->method('resolveToFeedUrl')
            ->with('https://unreachable.com')
            ->willReturn([
                'feedUrl' => null,
                'error' => 'Could not fetch URL: Connection refused',
            ]);

        $resolver = new FeedIoResolverService($feedDiscoveryService);

        $result = $resolver->resolve('https://unreachable.com');

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('feed-io', $result->getResolverName());
        $this->assertStringContainsString('Could not fetch URL', $result->getError());
    }
}
