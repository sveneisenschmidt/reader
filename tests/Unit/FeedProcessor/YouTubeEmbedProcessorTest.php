<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\FeedProcessor;

use App\Domain\Feed\Processor\YouTubeEmbedProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class YouTubeEmbedProcessorTest extends TestCase
{
    #[Test]
    #[DataProvider('youtubeUrlProvider')]
    public function processCreatesEmbedCodeForYouTubeUrls(
        string $url,
        string $expectedVideoId,
    ): void {
        $processor = new YouTubeEmbedProcessor();

        $item = ['link' => $url, 'excerpt' => 'Original content'];
        $result = $processor->process($item);

        $this->assertStringContainsString(
            'https://www.youtube.com/embed/'.$expectedVideoId,
            $result['excerpt'],
        );
        $this->assertStringContainsString('<iframe', $result['excerpt']);
        $this->assertStringContainsString(
            'allowfullscreen',
            $result['excerpt'],
        );
    }

    public static function youtubeUrlProvider(): array
    {
        return [
            'standard watch url' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'short url' => ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'embed url' => [
                'https://www.youtube.com/embed/dQw4w9WgXcQ',
                'dQw4w9WgXcQ',
            ],
            'v url' => ['https://www.youtube.com/v/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            'watch url with extra params' => [
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30',
                'dQw4w9WgXcQ',
            ],
        ];
    }

    #[Test]
    public function processDoesNotModifyNonYouTubeUrls(): void
    {
        $processor = new YouTubeEmbedProcessor();

        $item = ['link' => 'https://example.com/video', 'excerpt' => 'Content'];
        $result = $processor->process($item);

        $this->assertEquals('Content', $result['excerpt']);
    }

    #[Test]
    public function processEscapesVideoId(): void
    {
        $processor = new YouTubeEmbedProcessor();

        $item = [
            'link' => 'https://www.youtube.com/watch?v=abc123_-XYZ',
            'excerpt' => 'Original',
        ];
        $result = $processor->process($item);

        $this->assertStringContainsString('abc123_-XYZ', $result['excerpt']);
        $this->assertStringNotContainsString('<script', $result['excerpt']);
    }

    #[Test]
    #[DataProvider('youtubeUrlProvider')]
    public function supportsReturnsTrueForYouTubeUrls(
        string $url,
        string $expectedVideoId,
    ): void {
        $processor = new YouTubeEmbedProcessor();

        $this->assertTrue($processor->supports(['link' => $url]));
    }

    #[Test]
    public function supportsReturnsFalseForNonYouTubeUrls(): void
    {
        $processor = new YouTubeEmbedProcessor();

        $this->assertFalse(
            $processor->supports(['link' => 'https://example.com']),
        );
        $this->assertFalse(
            $processor->supports(['link' => 'https://vimeo.com/12345']),
        );
    }

    #[Test]
    public function supportsReturnsFalseWithoutLink(): void
    {
        $processor = new YouTubeEmbedProcessor();

        $this->assertFalse($processor->supports(['title' => 'test']));
    }

    #[Test]
    public function supportsReturnsFalseWithNonStringLink(): void
    {
        $processor = new YouTubeEmbedProcessor();

        $this->assertFalse($processor->supports(['link' => 123]));
        $this->assertFalse($processor->supports(['link' => null]));
    }

    #[Test]
    public function getPriorityReturns100(): void
    {
        $this->assertEquals(100, YouTubeEmbedProcessor::getPriority());
    }
}
