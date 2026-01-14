<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\FeedProcessor;

use App\FeedProcessor\TitleFromExcerptProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TitleFromExcerptProcessorTest extends TestCase
{
    #[Test]
    public function processCreatessTitleFromExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = ['title' => '', 'excerpt' => 'This is the excerpt'];
        $result = $processor->process($item);

        $this->assertEquals('This is the excerpt', $result['title']);
    }

    #[Test]
    public function processReturnsUntitledForEmptyExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = ['title' => '', 'excerpt' => ''];
        $result = $processor->process($item);

        $this->assertEquals('Untitled', $result['title']);
    }

    #[Test]
    public function processReturnsUntitledForWhitespaceOnlyExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = ['title' => '', 'excerpt' => '   '];
        $result = $processor->process($item);

        $this->assertEquals('Untitled', $result['title']);
    }

    #[Test]
    public function processReturnsUntitledForHtmlOnlyExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = ['title' => '', 'excerpt' => '<p></p>'];
        $result = $processor->process($item);

        $this->assertEquals('Untitled', $result['title']);
    }

    #[Test]
    public function processTruncatesLongExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $longText = str_repeat('a', 100);
        $item = ['title' => '', 'excerpt' => $longText];
        $result = $processor->process($item);

        $this->assertEquals(53, mb_strlen($result['title']));
        $this->assertStringEndsWith('...', $result['title']);
    }

    #[Test]
    public function processStripsHtmlFromExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = [
            'title' => '',
            'excerpt' => '<p>Hello <strong>World</strong></p>',
        ];
        $result = $processor->process($item);

        $this->assertEquals('Hello World', $result['title']);
    }

    #[Test]
    public function processDecodesHtmlEntities(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = ['title' => '', 'excerpt' => 'Hello &amp; World'];
        $result = $processor->process($item);

        $this->assertEquals('Hello & World', $result['title']);
    }

    #[Test]
    public function processHandlesMissingExcerpt(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $item = ['title' => ''];
        $result = $processor->process($item);

        $this->assertEquals('Untitled', $result['title']);
    }

    #[Test]
    public function supportsReturnsTrueForEmptyTitle(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $this->assertTrue($processor->supports(['title' => '']));
        $this->assertTrue($processor->supports(['title' => '   ']));
    }

    #[Test]
    public function supportsReturnsTrueForMissingTitle(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $this->assertTrue($processor->supports(['excerpt' => 'test']));
    }

    #[Test]
    public function supportsReturnsFalseForExistingTitle(): void
    {
        $processor = new TitleFromExcerptProcessor();

        $this->assertFalse(
            $processor->supports(['title' => 'Existing Title']),
        );
    }

    #[Test]
    public function getPriorityReturns200(): void
    {
        $this->assertEquals(200, TitleFromExcerptProcessor::getPriority());
    }
}
