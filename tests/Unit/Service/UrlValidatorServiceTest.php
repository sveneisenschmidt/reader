<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\FeedItem;
use App\Service\UrlValidatorService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlValidatorServiceTest extends TestCase
{
    private UrlValidatorService $service;

    protected function setUp(): void
    {
        $this->service = new UrlValidatorService();
    }

    #[Test]
    public function isUrlAllowedReturnsTrueWhenUrlMatchesItemLink(): void
    {
        $url = 'https://example.com/article';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: $url,
            source: 'Test Source',
            excerpt: 'Some content without the URL',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertTrue(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }

    #[Test]
    public function isUrlAllowedReturnsTrueWhenUrlExistsInContent(): void
    {
        $url = 'https://linked-site.com/page';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com/article',
            source: 'Test Source',
            excerpt: '<p>Check out <a href="https://linked-site.com/page">this link</a></p>',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertTrue(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }

    #[Test]
    public function isUrlAllowedReturnsFalseWhenUrlNotInContentOrLink(): void
    {
        $url = 'https://malicious.com/hack';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com/article',
            source: 'Test Source',
            excerpt: '<p>Check out <a href="https://safe-site.com/page">this link</a></p>',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertFalse(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }

    #[Test]
    public function isUrlAllowedReturnsFalseWhenContentIsEmpty(): void
    {
        $url = 'https://some-site.com/page';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com/article',
            source: 'Test Source',
            excerpt: '',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertFalse(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }

    #[Test]
    public function isUrlAllowedHandlesMultipleLinksInContent(): void
    {
        $url = 'https://third-site.com/page';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com/article',
            source: 'Test Source',
            excerpt: '<p>Links: <a href="https://first-site.com">first</a>, <a href="https://second-site.com">second</a>, <a href="https://third-site.com/page">third</a></p>',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertTrue(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }

    #[Test]
    public function isUrlAllowedReturnsFalseForUrlNotInMultipleLinks(): void
    {
        $url = 'https://not-in-content.com/page';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com/article',
            source: 'Test Source',
            excerpt: '<p>Links: <a href="https://first-site.com">first</a>, <a href="https://second-site.com">second</a></p>',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertFalse(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }

    #[Test]
    public function isUrlAllowedHandlesContentWithNoLinks(): void
    {
        $url = 'https://some-site.com/page';
        $feedItem = new FeedItem(
            guid: 'item-guid',
            subscriptionGuid: 'sub-guid',
            title: 'Test Item',
            link: 'https://example.com/article',
            source: 'Test Source',
            excerpt: '<p>This is content without any links.</p>',
            publishedAt: new \DateTimeImmutable(),
        );

        $this->assertFalse(
            $this->service->isUrlAllowedForFeedItem($url, $feedItem),
        );
    }
}
