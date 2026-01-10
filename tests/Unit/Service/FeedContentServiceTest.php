<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Service\FeedContentService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class FeedContentServiceTest extends TestCase
{
    #[Test]
    public function createGuidReturns16CharHash(): void
    {
        $service = $this->createService();

        $guid = $service->createGuid('https://example.com/feed');

        $this->assertEquals(16, strlen($guid));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $guid);
    }

    #[Test]
    public function createGuidReturnsSameHashForSameInput(): void
    {
        $service = $this->createService();

        $guid1 = $service->createGuid('https://example.com/feed');
        $guid2 = $service->createGuid('https://example.com/feed');

        $this->assertEquals($guid1, $guid2);
    }

    #[Test]
    public function createGuidReturnsDifferentHashForDifferentInput(): void
    {
        $service = $this->createService();

        $guid1 = $service->createGuid('https://example.com/feed1');
        $guid2 = $service->createGuid('https://example.com/feed2');

        $this->assertNotEquals($guid1, $guid2);
    }

    #[Test]
    public function sanitizeItemsCleansExcerpts(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer
            ->method('sanitize')
            ->willReturnCallback(fn ($text) => strip_tags($text));

        $service = $this->createService($sanitizer);

        $items = [
            ['excerpt' => '<p>Hello World</p>', 'title' => 'Test'],
            ['excerpt' => '<b>Bold</b> and <i>italic</i>', 'title' => 'Test2'],
        ];

        $result = $service->sanitizeItems($items);

        $this->assertEquals('Hello World', $result[0]['excerpt']);
        $this->assertEquals('Bold and italic', $result[1]['excerpt']);
    }

    #[Test]
    public function cleanExcerptDecodesHtmlEntities(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        $service = $this->createService($sanitizer);

        $result = $service->cleanExcerpt('Hello &amp; World');

        $this->assertEquals('Hello & World', $result);
    }

    #[Test]
    public function cleanExcerptTrimsWhitespace(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        $service = $this->createService($sanitizer);

        $result = $service->cleanExcerpt('  Hello World  ');

        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function createTitleFromExcerptReturnsUntitledForEmpty(): void
    {
        $service = $this->createService();

        $this->assertEquals('Untitled', $service->createTitleFromExcerpt(''));
        $this->assertEquals(
            'Untitled',
            $service->createTitleFromExcerpt('   '),
        );
        $this->assertEquals(
            'Untitled',
            $service->createTitleFromExcerpt('<p></p>'),
        );
    }

    #[Test]
    public function createTitleFromExcerptReturnsFullTextIfShort(): void
    {
        $service = $this->createService();

        $result = $service->createTitleFromExcerpt('Short title');

        $this->assertEquals('Short title', $result);
    }

    #[Test]
    public function createTitleFromExcerptTruncatesLongText(): void
    {
        $service = $this->createService();

        $longText = str_repeat('a', 100);
        $result = $service->createTitleFromExcerpt($longText);

        $this->assertEquals(53, mb_strlen($result)); // 50 + '...'
        $this->assertStringEndsWith('...', $result);
    }

    #[Test]
    public function createTitleFromExcerptStripsHtml(): void
    {
        $service = $this->createService();

        $result = $service->createTitleFromExcerpt(
            '<p>Hello <strong>World</strong></p>',
        );

        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function createTitleFromExcerptDecodesEntities(): void
    {
        $service = $this->createService();

        $result = $service->createTitleFromExcerpt('Hello &amp; World');

        $this->assertEquals('Hello & World', $result);
    }

    private function createService(
        ?HtmlSanitizerInterface $sanitizer = null,
    ): FeedContentService {
        return new FeedContentService(
            $sanitizer ?? $this->createStub(HtmlSanitizerInterface::class),
        );
    }
}
