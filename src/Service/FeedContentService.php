<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class FeedContentService
{
    public function __construct(
        private HtmlSanitizerInterface $feedContentSanitizer,
    ) {
    }

    public function createGuid(string $input): string
    {
        return substr(hash('sha256', $input), 0, 16);
    }

    #[Param(items: 'list<array<string, mixed>>')]
    #[Returns('list<array<string, mixed>>')]
    public function sanitizeItems(array $items): array
    {
        return array_map(function ($item) {
            $item['excerpt'] = $this->cleanExcerpt($item['excerpt']);

            return $item;
        }, $items);
    }

    public function cleanExcerpt(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->feedContentSanitizer->sanitize($text);

        return trim($text);
    }

    public function createTitleFromExcerpt(string $excerpt): string
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
