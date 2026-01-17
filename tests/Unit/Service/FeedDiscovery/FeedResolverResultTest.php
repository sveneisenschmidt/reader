<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service\FeedDiscovery;

use App\Domain\Discovery\FeedResolverResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedResolverResultTest extends TestCase
{
    #[Test]
    public function constructsWithDefaultErrorStatus(): void
    {
        $result = new FeedResolverResult('test-resolver');

        $this->assertEquals('test-resolver', $result->getResolverName());
        $this->assertEquals(FeedResolverResult::STATUS_ERROR, $result->getStatus());
        $this->assertNull($result->getFeedUrl());
        $this->assertNull($result->getError());
        $this->assertFalse($result->isSuccessful());
    }

    #[Test]
    public function constructsWithSuccessStatus(): void
    {
        $result = new FeedResolverResult(
            'test-resolver',
            FeedResolverResult::STATUS_SUCCESS,
            'https://example.com/feed.xml',
        );

        $this->assertEquals('test-resolver', $result->getResolverName());
        $this->assertEquals(FeedResolverResult::STATUS_SUCCESS, $result->getStatus());
        $this->assertEquals('https://example.com/feed.xml', $result->getFeedUrl());
        $this->assertNull($result->getError());
        $this->assertTrue($result->isSuccessful());
    }

    #[Test]
    public function constructsWithErrorMessage(): void
    {
        $result = new FeedResolverResult(
            'test-resolver',
            FeedResolverResult::STATUS_ERROR,
            null,
            'Something went wrong',
        );

        $this->assertEquals(FeedResolverResult::STATUS_ERROR, $result->getStatus());
        $this->assertNull($result->getFeedUrl());
        $this->assertEquals('Something went wrong', $result->getError());
        $this->assertFalse($result->isSuccessful());
    }

    #[Test]
    public function setFeedUrlChangesStatusToSuccess(): void
    {
        $result = new FeedResolverResult('test-resolver');
        $result->setError('Initial error');

        $returnedResult = $result->setFeedUrl('https://example.com/feed.xml');

        $this->assertSame($result, $returnedResult);
        $this->assertEquals(FeedResolverResult::STATUS_SUCCESS, $result->getStatus());
        $this->assertEquals('https://example.com/feed.xml', $result->getFeedUrl());
        $this->assertNull($result->getError());
        $this->assertTrue($result->isSuccessful());
    }

    #[Test]
    public function setErrorChangesStatusToError(): void
    {
        $result = new FeedResolverResult(
            'test-resolver',
            FeedResolverResult::STATUS_SUCCESS,
            'https://example.com/feed.xml',
        );

        $returnedResult = $result->setError('Something failed');

        $this->assertSame($result, $returnedResult);
        $this->assertEquals(FeedResolverResult::STATUS_ERROR, $result->getStatus());
        $this->assertNull($result->getFeedUrl());
        $this->assertEquals('Something failed', $result->getError());
        $this->assertFalse($result->isSuccessful());
    }
}
