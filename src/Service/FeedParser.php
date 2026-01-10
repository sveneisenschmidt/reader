<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Feed\FeedInterface;
use Laminas\Feed\Reader\Reader;

class FeedParser
{
    public function parse(string $content, string $feedUrl): array
    {
        try {
            $feed = Reader::importString($content);

            return $this->extractFeedData($feed, $feedUrl);
        } catch (\Exception $e) {
            return ['title' => '', 'items' => []];
        }
    }

    public function isValid(string $content): bool
    {
        try {
            $feed = Reader::importString($content);

            return $feed->count() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function extractFeedData(
        FeedInterface $feed,
        string $feedUrl,
    ): array {
        $feedGuid = $this->createGuid($feedUrl);
        $title = $feed->getTitle() ?? '';
        $items = [];

        foreach ($feed as $entry) {
            $items[] = $this->extractEntryData($entry, $title, $feedGuid);
        }

        return ['title' => $title, 'items' => $items];
    }

    private function extractEntryData(
        EntryInterface $entry,
        string $feedTitle,
        string $feedGuid,
    ): array {
        $link = $entry->getLink() ?? '';
        $id = $entry->getId() ?? $link;
        $excerpt = $entry->getDescription() ?? ($entry->getContent() ?? '');
        $itemTitle = $entry->getTitle() ?? '';

        if (empty(trim($itemTitle))) {
            $itemTitle = $this->createTitleFromExcerpt($excerpt);
        }

        $date = $entry->getDateModified() ?? $entry->getDateCreated();

        return [
            'guid' => $this->createGuid($link ?: $id),
            'title' => $itemTitle,
            'link' => $link,
            'source' => $feedTitle,
            'feedGuid' => $feedGuid,
            'date' => $date ?? new \DateTime('now'),
            'excerpt' => $excerpt,
        ];
    }

    public function createGuid(string $input): string
    {
        return substr(hash('sha256', $input), 0, 16);
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
