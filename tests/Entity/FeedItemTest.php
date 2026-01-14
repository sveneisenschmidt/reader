<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity;

use App\Entity\FeedItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedItemTest extends TestCase
{
    private function createFeedItem(): FeedItem
    {
        return new FeedItem(
            guid: "abc123def456",
            subscriptionGuid: "feed123456789",
            title: "Test Article",
            link: "https://example.com/article",
            source: "Test Blog",
            excerpt: "This is a test excerpt",
            publishedAt: new \DateTimeImmutable("2024-01-15 10:00:00"),
        );
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $item = $this->createFeedItem();

        $this->assertEquals("abc123def456", $item->getGuid());
        $this->assertEquals("feed123456789", $item->getSubscriptionGuid());
        $this->assertEquals("Test Article", $item->getTitle());
        $this->assertEquals("https://example.com/article", $item->getLink());
        $this->assertEquals("Test Blog", $item->getSource());
        $this->assertEquals("This is a test excerpt", $item->getExcerpt());
        $this->assertEquals(
            "2024-01-15",
            $item->getPublishedAt()->format("Y-m-d"),
        );
    }

    #[Test]
    public function idIsNullBeforePersist(): void
    {
        $item = $this->createFeedItem();

        $this->assertNull($item->getId());
    }

    #[Test]
    public function fetchedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $item = $this->createFeedItem();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $item->getFetchedAt());
        $this->assertLessThanOrEqual($after, $item->getFetchedAt());
    }

    #[Test]
    public function setTitleUpdatesTitle(): void
    {
        $item = $this->createFeedItem();

        $result = $item->setTitle("New Title");

        $this->assertEquals("New Title", $item->getTitle());
        $this->assertSame($item, $result);
    }

    #[Test]
    public function setLinkUpdatesLink(): void
    {
        $item = $this->createFeedItem();

        $result = $item->setLink("https://new.example.com");

        $this->assertEquals("https://new.example.com", $item->getLink());
        $this->assertSame($item, $result);
    }

    #[Test]
    public function setSourceUpdatesSource(): void
    {
        $item = $this->createFeedItem();

        $result = $item->setSource("New Source");

        $this->assertEquals("New Source", $item->getSource());
        $this->assertSame($item, $result);
    }

    #[Test]
    public function setExcerptUpdatesExcerpt(): void
    {
        $item = $this->createFeedItem();

        $result = $item->setExcerpt("New excerpt content");

        $this->assertEquals("New excerpt content", $item->getExcerpt());
        $this->assertSame($item, $result);
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $item = $this->createFeedItem();

        $array = $item->toArray();

        $this->assertEquals("abc123def456", $array["guid"]);
        $this->assertEquals("feed123456789", $array["sguid"]);
        $this->assertEquals("Test Article", $array["title"]);
        $this->assertEquals("https://example.com/article", $array["link"]);
        $this->assertEquals("Test Blog", $array["source"]);
        $this->assertEquals("This is a test excerpt", $array["excerpt"]);
        $this->assertInstanceOf(\DateTimeImmutable::class, $array["date"]);
    }
}
