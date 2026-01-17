<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\Feed\Processor;

use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;

class TitleFromExcerptProcessor implements FeedItemProcessorInterface
{
    #[Param(item: 'array<string, mixed>')]
    #[Returns('array<string, mixed>')]
    public function process(array $item): array
    {
        $item['title'] = $this->createTitleFromExcerpt($item['excerpt'] ?? '');

        return $item;
    }

    #[Param(item: 'array<string, mixed>')]
    public function supports(array $item): bool
    {
        return !isset($item['title']) || empty(trim($item['title']));
    }

    public static function getPriority(): int
    {
        return 200;
    }

    private function createTitleFromExcerpt(string $excerpt): string
    {
        $text = strip_tags($excerpt);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim($text);

        if (empty($text)) {
            return 'Untitled';
        }

        if (mb_strlen($text) <= 50) {
            return $text;
        }

        return mb_substr($text, 0, 50).'...';
    }
}
