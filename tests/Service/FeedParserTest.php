<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Service\FeedParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedParserTest extends TestCase
{
    private FeedParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FeedParser();
    }

    #[Test]
    public function parseValidRssFeed(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <link>https://example.com</link>
        <item>
            <title>Test Item</title>
            <link>https://example.com/item1</link>
            <description>Test description</description>
            <pubDate>Mon, 01 Jan 2024 12:00:00 +0000</pubDate>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertEquals("Test Feed", $result["title"]);
        $this->assertCount(1, $result["items"]);
        $this->assertEquals("Test Item", $result["items"][0]["title"]);
        $this->assertEquals(
            "https://example.com/item1",
            $result["items"][0]["link"],
        );
        $this->assertEquals("Test description", $result["items"][0]["excerpt"]);
        $this->assertEquals("Test Feed", $result["items"][0]["source"]);
    }

    #[Test]
    public function parseValidAtomFeed(): void
    {
        $atom = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Atom Feed</title>
    <entry>
        <title>Atom Entry</title>
        <link href="https://example.com/entry1"/>
        <id>urn:uuid:1234</id>
        <updated>2024-01-01T12:00:00Z</updated>
        <summary>Atom summary</summary>
    </entry>
</feed>
XML;

        $result = $this->parser->parse($atom, "https://example.com/atom.xml");

        $this->assertEquals("Atom Feed", $result["title"]);
        $this->assertCount(1, $result["items"]);
        $this->assertEquals("Atom Entry", $result["items"][0]["title"]);
    }

    #[Test]
    public function parseInvalidFeedReturnsEmptyResult(): void
    {
        $result = $this->parser->parse(
            "not valid xml",
            "https://example.com/feed.xml",
        );

        $this->assertEquals("", $result["title"]);
        $this->assertEmpty($result["items"]);
    }

    #[Test]
    public function parseEmptyContentReturnsEmptyResult(): void
    {
        $result = $this->parser->parse("", "https://example.com/feed.xml");

        $this->assertEquals("", $result["title"]);
        $this->assertEmpty($result["items"]);
    }

    #[Test]
    public function isValidReturnsTrueForValidRss(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test</title>
    </channel>
</rss>
XML;

        $this->assertTrue($this->parser->isValid($rss));
    }

    #[Test]
    public function isValidReturnsTrueForValidAtom(): void
    {
        $atom = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Test</title>
</feed>
XML;

        $this->assertTrue($this->parser->isValid($atom));
    }

    #[Test]
    public function isValidReturnsFalseForInvalidContent(): void
    {
        $this->assertFalse($this->parser->isValid("not xml"));
    }

    #[Test]
    public function isValidReturnsFalseForEmptyContent(): void
    {
        $this->assertFalse($this->parser->isValid(""));
    }

    #[Test]
    public function createGuidGenerates16CharHash(): void
    {
        $guid = $this->parser->createGuid("https://example.com/item");

        $this->assertEquals(16, strlen($guid));
        $this->assertMatchesRegularExpression("/^[a-f0-9]{16}$/", $guid);
    }

    #[Test]
    public function createGuidIsDeterministic(): void
    {
        $guid1 = $this->parser->createGuid("https://example.com/item");
        $guid2 = $this->parser->createGuid("https://example.com/item");

        $this->assertEquals($guid1, $guid2);
    }

    #[Test]
    public function createGuidDiffersForDifferentInputs(): void
    {
        $guid1 = $this->parser->createGuid("https://example.com/item1");
        $guid2 = $this->parser->createGuid("https://example.com/item2");

        $this->assertNotEquals($guid1, $guid2);
    }

    #[Test]
    public function parseItemWithoutTitleUsesExcerpt(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title></title>
            <link>https://example.com/item1</link>
            <description>This is the description that will be used as title</description>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertStringStartsWith(
            "This is the description",
            $result["items"][0]["title"],
        );
    }

    #[Test]
    public function parseItemWithLongExcerptTruncatesTitle(): void
    {
        $longDescription = str_repeat("a", 100);
        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title></title>
            <link>https://example.com/item1</link>
            <description>{$longDescription}</description>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertEquals(53, strlen($result["items"][0]["title"])); // 50 + "..."
        $this->assertStringEndsWith("...", $result["items"][0]["title"]);
    }

    #[Test]
    public function parseItemWithNoTitleAndNoDescriptionUsesUntitled(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <link>https://example.com/item1</link>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertEquals("Untitled", $result["items"][0]["title"]);
    }

    #[Test]
    public function parseItemWithHtmlInExcerptStripsTagsForTitle(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title></title>
            <link>https://example.com/item1</link>
            <description><![CDATA[<p><strong>Bold</strong> text</p>]]></description>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertEquals("Bold text", $result["items"][0]["title"]);
    }

    #[Test]
    public function parseItemWithHtmlEntitiesDecodesThemForTitle(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title></title>
            <link>https://example.com/item1</link>
            <description>&amp;quot;Test&amp;quot; &amp;amp; more</description>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertStringContainsString(
            '"Test"',
            $result["items"][0]["title"],
        );
    }

    #[Test]
    public function parseMultipleItems(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title>Item 1</title>
            <link>https://example.com/item1</link>
        </item>
        <item>
            <title>Item 2</title>
            <link>https://example.com/item2</link>
        </item>
        <item>
            <title>Item 3</title>
            <link>https://example.com/item3</link>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertCount(3, $result["items"]);
        $this->assertEquals("Item 1", $result["items"][0]["title"]);
        $this->assertEquals("Item 2", $result["items"][1]["title"]);
        $this->assertEquals("Item 3", $result["items"][2]["title"]);
    }

    #[Test]
    public function parseItemWithContentInsteadOfDescription(): void
    {
        $atom = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Test Feed</title>
    <entry>
        <title>Test Entry</title>
        <link href="https://example.com/entry1"/>
        <id>urn:uuid:1234</id>
        <updated>2024-01-01T12:00:00Z</updated>
        <content type="html">Full content here</content>
    </entry>
</feed>
XML;

        $result = $this->parser->parse($atom, "https://example.com/atom.xml");

        $this->assertNotEmpty($result["items"][0]["excerpt"]);
    }

    #[Test]
    public function parseItemUsesIdWhenLinkIsMissing(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title>Test Item</title>
            <guid>unique-id-123</guid>
            <description>Description</description>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        // The guid should be created based on the id when link is missing
        $this->assertNotEmpty($result["items"][0]["guid"]);
    }

    #[Test]
    public function parseFeedWithNoTitle(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <item>
            <title>Test Item</title>
            <link>https://example.com/item1</link>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertEquals("", $result["title"]);
    }

    #[Test]
    public function parseItemWithDateModified(): void
    {
        $atom = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Test Feed</title>
    <entry>
        <title>Test Entry</title>
        <link href="https://example.com/entry1"/>
        <id>urn:uuid:1234</id>
        <published>2024-01-01T10:00:00Z</published>
        <updated>2024-01-01T12:00:00Z</updated>
    </entry>
</feed>
XML;

        $result = $this->parser->parse($atom, "https://example.com/atom.xml");

        $this->assertInstanceOf(
            \DateTimeInterface::class,
            $result["items"][0]["date"],
        );
    }

    #[Test]
    public function parseItemWithOnlyDateCreated(): void
    {
        $atom = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Test Feed</title>
    <entry>
        <title>Test Entry</title>
        <link href="https://example.com/entry1"/>
        <id>urn:uuid:1234</id>
        <published>2024-01-01T10:00:00Z</published>
    </entry>
</feed>
XML;

        $result = $this->parser->parse($atom, "https://example.com/atom.xml");

        $this->assertInstanceOf(
            \DateTimeInterface::class,
            $result["items"][0]["date"],
        );
    }

    #[Test]
    public function parseItemWithNoDateUsesCurrentDate(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title>Test Item</title>
            <link>https://example.com/item1</link>
        </item>
    </channel>
</rss>
XML;

        $before = new \DateTime("now");
        $result = $this->parser->parse($rss, "https://example.com/feed.xml");
        $after = new \DateTime("now");

        $itemDate = $result["items"][0]["date"];
        $this->assertInstanceOf(\DateTimeInterface::class, $itemDate);
        $this->assertGreaterThanOrEqual($before, $itemDate);
        $this->assertLessThanOrEqual($after, $itemDate);
    }

    #[Test]
    public function feedGuidIsConsistentForSameFeedUrl(): void
    {
        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Test Feed</title>
        <item>
            <title>Item 1</title>
            <link>https://example.com/item1</link>
        </item>
        <item>
            <title>Item 2</title>
            <link>https://example.com/item2</link>
        </item>
    </channel>
</rss>
XML;

        $result = $this->parser->parse($rss, "https://example.com/feed.xml");

        $this->assertEquals(
            $result["items"][0]["feedGuid"],
            $result["items"][1]["feedGuid"],
        );
    }
}
