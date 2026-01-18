<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\Feed\Entity\Subscription;
use App\Service\OpmlService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OpmlServiceTest extends TestCase
{
    private OpmlService $service;

    protected function setUp(): void
    {
        $this->service = new OpmlService();
    }

    #[Test]
    public function parseReturnsEmptyArrayForInvalidXml(): void
    {
        $result = $this->service->parse('not valid xml');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parseReturnsEmptyArrayForEmptyOpml(): void
    {
        $opml = '<?xml version="1.0"?><opml version="2.0"><head></head><body></body></opml>';

        $result = $this->service->parse($opml);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parseExtractsFeedFromSimpleOutline(): void
    {
        $opml = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline type="rss" text="Test Feed" xmlUrl="https://example.com/feed.xml"/>
                </body>
            </opml>';

        $result = $this->service->parse($opml);

        $this->assertCount(1, $result);
        $this->assertSame('https://example.com/feed.xml', $result[0]['url']);
        $this->assertSame('Test Feed', $result[0]['title']);
        $this->assertNull($result[0]['folder']);
    }

    #[Test]
    public function parseExtractsMultipleFeeds(): void
    {
        $opml = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline type="rss" text="Feed 1" xmlUrl="https://example.com/feed1.xml"/>
                    <outline type="rss" text="Feed 2" xmlUrl="https://example.com/feed2.xml"/>
                </body>
            </opml>';

        $result = $this->service->parse($opml);

        $this->assertCount(2, $result);
        $this->assertSame('https://example.com/feed1.xml', $result[0]['url']);
        $this->assertSame('https://example.com/feed2.xml', $result[1]['url']);
    }

    #[Test]
    public function parseExtractsFolderFromNestedOutline(): void
    {
        $opml = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline text="Tech">
                        <outline type="rss" text="Tech Feed" xmlUrl="https://example.com/tech.xml"/>
                    </outline>
                </body>
            </opml>';

        $result = $this->service->parse($opml);

        $this->assertCount(1, $result);
        $this->assertSame('https://example.com/tech.xml', $result[0]['url']);
        $this->assertSame('Tech Feed', $result[0]['title']);
        $this->assertSame('Tech', $result[0]['folder']);
    }

    #[Test]
    public function parseHandlesMixedFoldersAndFeeds(): void
    {
        $opml = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline type="rss" text="Ungrouped" xmlUrl="https://example.com/ungrouped.xml"/>
                    <outline text="Folder A">
                        <outline type="rss" text="Feed A1" xmlUrl="https://example.com/a1.xml"/>
                        <outline type="rss" text="Feed A2" xmlUrl="https://example.com/a2.xml"/>
                    </outline>
                    <outline text="Folder B">
                        <outline type="rss" text="Feed B1" xmlUrl="https://example.com/b1.xml"/>
                    </outline>
                </body>
            </opml>';

        $result = $this->service->parse($opml);

        $this->assertCount(4, $result);

        $this->assertNull($result[0]['folder']);
        $this->assertSame('Folder A', $result[1]['folder']);
        $this->assertSame('Folder A', $result[2]['folder']);
        $this->assertSame('Folder B', $result[3]['folder']);
    }

    #[Test]
    public function generateCreatesValidOpml(): void
    {
        $subscriptions = [];

        $result = $this->service->generate($subscriptions);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $result);
        $this->assertStringContainsString('<opml version="2.0">', $result);
        $this->assertStringContainsString('<title>Reader Subscriptions</title>', $result);
    }

    #[Test]
    public function generateIncludesSubscription(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getName')->willReturn('Test Feed');
        $subscription->method('getUrl')->willReturn('https://example.com/feed.xml');
        $subscription->method('getFolder')->willReturn(null);

        $result = $this->service->generate([$subscription]);

        $this->assertStringContainsString('type="rss"', $result);
        $this->assertStringContainsString('text="Test Feed"', $result);
        $this->assertStringContainsString('xmlUrl="https://example.com/feed.xml"', $result);
    }

    #[Test]
    public function generateGroupsSubscriptionsByFolder(): void
    {
        $sub1 = $this->createMock(Subscription::class);
        $sub1->method('getName')->willReturn('Feed 1');
        $sub1->method('getUrl')->willReturn('https://example.com/feed1.xml');
        $sub1->method('getFolder')->willReturn('Tech');

        $sub2 = $this->createMock(Subscription::class);
        $sub2->method('getName')->willReturn('Feed 2');
        $sub2->method('getUrl')->willReturn('https://example.com/feed2.xml');
        $sub2->method('getFolder')->willReturn('Tech');

        $result = $this->service->generate([$sub1, $sub2]);

        // Verify folder structure exists
        $xml = new \SimpleXMLElement($result);
        $folderOutline = $xml->body->outline[0];

        $this->assertSame('Tech', (string) $folderOutline['text']);
        $this->assertCount(2, $folderOutline->outline);
    }

    #[Test]
    public function generatePlacesUngroupedFeedsFirst(): void
    {
        $ungrouped = $this->createMock(Subscription::class);
        $ungrouped->method('getName')->willReturn('Ungrouped');
        $ungrouped->method('getUrl')->willReturn('https://example.com/ungrouped.xml');
        $ungrouped->method('getFolder')->willReturn(null);

        $grouped = $this->createMock(Subscription::class);
        $grouped->method('getName')->willReturn('Grouped');
        $grouped->method('getUrl')->willReturn('https://example.com/grouped.xml');
        $grouped->method('getFolder')->willReturn('Folder');

        $result = $this->service->generate([$grouped, $ungrouped]);

        $xml = new \SimpleXMLElement($result);

        // First outline should be ungrouped feed
        $this->assertSame('Ungrouped', (string) $xml->body->outline[0]['text']);
        // Second outline should be folder
        $this->assertSame('Folder', (string) $xml->body->outline[1]['text']);
    }

    #[Test]
    public function generateHandlesEmptyFolder(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getName')->willReturn('Test Feed');
        $subscription->method('getUrl')->willReturn('https://example.com/feed.xml');
        $subscription->method('getFolder')->willReturn('');

        $result = $this->service->generate([$subscription]);

        $xml = new \SimpleXMLElement($result);

        // Should be treated as ungrouped
        $this->assertSame('Test Feed', (string) $xml->body->outline[0]['text']);
        $this->assertSame('https://example.com/feed.xml', (string) $xml->body->outline[0]['xmlUrl']);
    }

    #[Test]
    public function generateOutputIsFormattedXml(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getName')->willReturn('Test');
        $subscription->method('getUrl')->willReturn('https://example.com/feed.xml');
        $subscription->method('getFolder')->willReturn(null);

        $result = $this->service->generate([$subscription]);

        // Formatted XML should contain newlines and indentation
        $this->assertStringContainsString("\n", $result);
    }

    #[Test]
    public function parseAndGenerateRoundTrip(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getName')->willReturn('Test Feed');
        $subscription->method('getUrl')->willReturn('https://example.com/feed.xml');
        $subscription->method('getFolder')->willReturn('Tech');

        $opml = $this->service->generate([$subscription]);
        $parsed = $this->service->parse($opml);

        $this->assertCount(1, $parsed);
        $this->assertSame('https://example.com/feed.xml', $parsed[0]['url']);
        $this->assertSame('Test Feed', $parsed[0]['title']);
        $this->assertSame('Tech', $parsed[0]['folder']);
    }
}
