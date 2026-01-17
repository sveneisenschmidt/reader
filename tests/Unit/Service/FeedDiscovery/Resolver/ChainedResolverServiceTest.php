<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service\FeedDiscovery\Resolver;

use App\Domain\Discovery\FeedResolverInterface;
use App\Domain\Discovery\FeedResolverResult;
use App\Domain\Discovery\Resolver\ChainedResolverService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChainedResolverServiceTest extends TestCase
{
    #[Test]
    public function supportsAlwaysReturnsTrue(): void
    {
        $resolver = new ChainedResolverService([]);

        $this->assertTrue($resolver->supports('anything'));
        $this->assertTrue($resolver->supports('https://example.com'));
    }

    #[Test]
    public function resolveReturnsErrorWhenNoResolvers(): void
    {
        $resolver = new ChainedResolverService([]);

        $result = $resolver->resolve('https://example.com');

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('chained', $result->getResolverName());
        $this->assertEquals('Could not resolve feed URL', $result->getError());
    }

    #[Test]
    public function resolveSkipsResolversThatDoNotSupport(): void
    {
        $unsupportedResolver = $this->createMock(FeedResolverInterface::class);
        $unsupportedResolver
            ->expects($this->once())
            ->method('supports')
            ->with('https://example.com')
            ->willReturn(false);
        $unsupportedResolver
            ->expects($this->never())
            ->method('resolve');

        $supportedResolver = $this->createMock(FeedResolverInterface::class);
        $supportedResolver
            ->expects($this->once())
            ->method('supports')
            ->with('https://example.com')
            ->willReturn(true);
        $supportedResolver
            ->expects($this->once())
            ->method('resolve')
            ->with('https://example.com')
            ->willReturn(
                (new FeedResolverResult('supported'))->setFeedUrl('https://example.com/feed.xml'),
            );

        $resolver = new ChainedResolverService([$unsupportedResolver, $supportedResolver]);

        $result = $resolver->resolve('https://example.com');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('supported', $result->getResolverName());
        $this->assertEquals('https://example.com/feed.xml', $result->getFeedUrl());
    }

    #[Test]
    public function resolveReturnsFirstSuccessfulResult(): void
    {
        $firstResolver = $this->createMock(FeedResolverInterface::class);
        $firstResolver->method('supports')->willReturn(true);
        $firstResolver
            ->method('resolve')
            ->willReturn(
                (new FeedResolverResult('first'))->setFeedUrl('https://first.com/feed.xml'),
            );

        $secondResolver = $this->createMock(FeedResolverInterface::class);
        $secondResolver
            ->expects($this->never())
            ->method('supports');
        $secondResolver
            ->expects($this->never())
            ->method('resolve');

        $resolver = new ChainedResolverService([$firstResolver, $secondResolver]);

        $result = $resolver->resolve('https://example.com');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('first', $result->getResolverName());
    }

    #[Test]
    public function resolveReturnsErrorResultImmediately(): void
    {
        $failingResolver = $this->createMock(FeedResolverInterface::class);
        $failingResolver->method('supports')->willReturn(true);
        $failingResolver
            ->method('resolve')
            ->willReturn(
                (new FeedResolverResult('failing'))->setError('Something went wrong'),
            );

        $secondResolver = $this->createMock(FeedResolverInterface::class);
        $secondResolver
            ->expects($this->never())
            ->method('supports');

        $resolver = new ChainedResolverService([$failingResolver, $secondResolver]);

        $result = $resolver->resolve('https://example.com');

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('failing', $result->getResolverName());
        $this->assertEquals('Something went wrong', $result->getError());
    }

    #[Test]
    public function resolveReturnsChainedErrorWhenAllResolversUnsupported(): void
    {
        $resolver1 = $this->createMock(FeedResolverInterface::class);
        $resolver1->method('supports')->willReturn(false);

        $resolver2 = $this->createMock(FeedResolverInterface::class);
        $resolver2->method('supports')->willReturn(false);

        $resolver = new ChainedResolverService([$resolver1, $resolver2]);

        $result = $resolver->resolve('https://example.com');

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('chained', $result->getResolverName());
        $this->assertEquals('Could not resolve feed URL', $result->getError());
    }
}
