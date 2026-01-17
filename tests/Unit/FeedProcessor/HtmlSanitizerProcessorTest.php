<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\FeedProcessor;

use App\Domain\Feed\Processor\HtmlSanitizerProcessor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class HtmlSanitizerProcessorTest extends TestCase
{
    #[Test]
    public function processCleanExcerpt(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer
            ->method('sanitize')
            ->willReturnCallback(fn ($text) => strip_tags($text));

        $processor = new HtmlSanitizerProcessor($sanitizer);

        $item = ['excerpt' => '<p>Hello World</p>', 'title' => 'Test'];
        $result = $processor->process($item);

        $this->assertEquals('Hello World', $result['excerpt']);
        $this->assertEquals('Test', $result['title']);
    }

    #[Test]
    public function processDecodesHtmlEntities(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        $processor = new HtmlSanitizerProcessor($sanitizer);

        $item = ['excerpt' => 'Hello &amp; World'];
        $result = $processor->process($item);

        $this->assertEquals('Hello & World', $result['excerpt']);
    }

    #[Test]
    public function processTrimsWhitespace(): void
    {
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);

        $processor = new HtmlSanitizerProcessor($sanitizer);

        $item = ['excerpt' => '  Hello World  '];
        $result = $processor->process($item);

        $this->assertEquals('Hello World', $result['excerpt']);
    }

    #[Test]
    public function supportsReturnsTrueWithExcerpt(): void
    {
        $processor = new HtmlSanitizerProcessor(
            $this->createStub(HtmlSanitizerInterface::class),
        );

        $this->assertTrue($processor->supports(['excerpt' => 'test']));
    }

    #[Test]
    public function supportsReturnsFalseWithoutExcerpt(): void
    {
        $processor = new HtmlSanitizerProcessor(
            $this->createStub(HtmlSanitizerInterface::class),
        );

        $this->assertFalse($processor->supports(['title' => 'test']));
    }

    #[Test]
    public function supportsReturnsFalseWithNonStringExcerpt(): void
    {
        $processor = new HtmlSanitizerProcessor(
            $this->createStub(HtmlSanitizerInterface::class),
        );

        $this->assertFalse($processor->supports(['excerpt' => 123]));
        $this->assertFalse($processor->supports(['excerpt' => null]));
    }

    #[Test]
    public function getPriorityReturns300(): void
    {
        $this->assertEquals(300, HtmlSanitizerProcessor::getPriority());
    }
}
