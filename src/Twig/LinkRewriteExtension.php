<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LinkRewriteExtension extends AbstractExtension
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'rewrite_links',
                [$this, 'rewriteLinks'],
                [
                    'is_safe' => ['html'],
                ],
            ),
        ];
    }

    public function rewriteLinks(string $html, string $fguid): string
    {
        if (empty($html)) {
            return $html;
        }

        return preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) use ($fguid) {
                $before = $matches[1];
                $originalUrl = $matches[2];
                $after = $matches[3];

                // Skip anchor links and javascript
                if (
                    str_starts_with($originalUrl, '#')
                    || str_starts_with($originalUrl, 'javascript:')
                ) {
                    return $matches[0];
                }

                $newUrl = $this->urlGenerator->generate('feed_item_open', [
                    'fguid' => $fguid,
                    'url' => $originalUrl,
                ]);

                return sprintf(
                    '<a %shref="%s"%s target="_blank" rel="noopener noreferrer">',
                    $before,
                    htmlspecialchars($newUrl, ENT_QUOTES, 'UTF-8'),
                    $after,
                );
            },
            $html,
        ) ?? $html;
    }
}
