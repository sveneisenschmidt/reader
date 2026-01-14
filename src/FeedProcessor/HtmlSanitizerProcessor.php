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
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class HtmlSanitizerProcessor implements FeedItemProcessorInterface
{
    public function __construct(
        private HtmlSanitizerInterface $feedContentSanitizer,
    ) {
    }

    #[Param(item: 'array<string, mixed>')]
    #[Returns('array<string, mixed>')]
    public function process(array $item): array
    {
        $item['excerpt'] = $this->cleanExcerpt($item['excerpt']);

        return $item;
    }

    #[Param(item: 'array<string, mixed>')]
    public function supports(array $item): bool
    {
        return isset($item['excerpt']) && is_string($item['excerpt']);
    }

    public static function getPriority(): int
    {
        return 300;
    }

    private function cleanExcerpt(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->feedContentSanitizer->sanitize($text);

        return trim($text);
    }
}
