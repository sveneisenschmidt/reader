<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\FeedItem;
use Symfony\Component\DomCrawler\Crawler;

class UrlValidatorService
{
    /**
     * Validates if a URL is allowed for the given feed item.
     * A URL is allowed if it matches the item's link or is found in the item's content.
     */
    public function isUrlAllowedForFeedItem(string $url, FeedItem $feedItem): bool
    {
        if ($feedItem->getLink() === $url) {
            return true;
        }

        $content = $feedItem->getExcerpt();
        if (empty($content)) {
            return false;
        }

        $crawler = new Crawler($content);
        $hrefs = $crawler->filter('a')->each(fn ($node) => $node->attr('href'));

        return in_array($url, $hrefs, true);
    }
}
