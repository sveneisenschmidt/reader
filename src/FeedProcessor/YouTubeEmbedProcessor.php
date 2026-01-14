<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\FeedProcessor;

use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class YouTubeEmbedProcessor implements FeedItemProcessorInterface
{
    private const YOUTUBE_PATTERNS = [
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
    ];

    #[Param(item: 'array<string, mixed>')]
    #[Returns('array<string, mixed>')]
    public function process(array $item): array
    {
        $videoId = $this->extractVideoId($item['link']);

        if ($videoId !== null) {
            $item['excerpt'] = $this->createEmbedCode($videoId);
        }

        return $item;
    }

    #[Param(item: 'array<string, mixed>')]
    public function supports(array $item): bool
    {
        if (!isset($item['link']) || !is_string($item['link'])) {
            return false;
        }

        return $this->extractVideoId($item['link']) !== null;
    }

    public static function getPriority(): int
    {
        return 300;
    }

    private function extractVideoId(string $url): ?string
    {
        foreach (self::YOUTUBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function createEmbedCode(string $videoId): string
    {
        $escapedId = htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<iframe width="560" height="315" src="https://www.youtube.com/embed/%s" '
            .'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; '
            .'gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
            $escapedId,
        );
    }
}
