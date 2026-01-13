<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use FeedIo\FeedInterface;
use FeedIo\FeedIo;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use Psr\Log\LoggerInterface;

class FeedReaderService
{
    public function __construct(
        private FeedIo $feedIo,
        private FeedContentService $contentService,
        private FeedPersistenceService $persistenceService,
        private LoggerInterface $logger,
    ) {
    }

    #[Returns('array{title: string, items: list<array<string, mixed>>}')]
    public function fetchFeed(string $url): array
    {
        $result = $this->feedIo->read($url);
        $feed = $result->getFeed();

        return $this->extractFeedData($feed, $url);
    }

    public function getFeedTitle(string $url): string
    {
        $result = $this->feedIo->read($url);
        $feed = $result->getFeed();

        return $feed->getTitle() ?? '';
    }

    #[Returns('array{title: string, items: list<array<string, mixed>>}')]
    public function fetchAndPersistFeed(string $url): array
    {
        $feedData = $this->fetchFeed($url);
        $feedData['items'] = $this->contentService->sanitizeItems(
            $feedData['items'],
        );
        $this->persistenceService->persistFeedItems($feedData['items']);

        return $feedData;
    }

    #[Param(feedUrls: 'list<string>')]
    public function refreshAllFeeds(array $feedUrls): int
    {
        $count = 0;

        foreach ($feedUrls as $feedUrl) {
            try {
                $feedData = $this->fetchFeed($feedUrl);
                $feedData['items'] = $this->contentService->sanitizeItems(
                    $feedData['items'],
                );
                $this->persistenceService->persistFeedItems($feedData['items']);
                $count += count($feedData['items']);
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch feed', [
                    'url' => $feedUrl,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $count;
    }

    #[Returns('array{title: string, items: list<array<string, mixed>>}')]
    private function extractFeedData(
        FeedInterface $feed,
        string $feedUrl,
    ): array {
        $subscriptionGuid = $this->createSubscriptionGuid($feedUrl);
        $title = $feed->getTitle() ?? '';
        $items = [];

        foreach ($feed as $item) {
            $items[] = $this->extractItemData($item, $title, $subscriptionGuid);
        }

        return ['title' => $title, 'items' => $items];
    }

    #[Returns('array<string, mixed>')]
    private function extractItemData(
        \FeedIo\Feed\ItemInterface $item,
        string $feedTitle,
        string $subscriptionGuid,
    ): array {
        $link = $item->getLink() ?? '';
        $id = $item->getPublicId() ?? $link;
        $excerpt = $item->getSummary() ?? ($item->getContent() ?? '');
        $itemTitle = $item->getTitle() ?? '';

        if (empty(trim($itemTitle))) {
            $itemTitle = $this->contentService->createTitleFromExcerpt(
                $excerpt,
            );
        }

        $date = $item->getLastModified();

        return [
            'guid' => $this->createFeedItemGuid(
                $subscriptionGuid,
                $link ?: $id,
            ),
            'title' => $itemTitle,
            'link' => $link,
            'source' => $feedTitle,
            'subscriptionGuid' => $subscriptionGuid,
            'date' => $date ?? new \DateTime('now'),
            'excerpt' => $excerpt,
        ];
    }

    private function createSubscriptionGuid(string $feedUrl): string
    {
        return substr(hash('sha256', $feedUrl), 0, 16);
    }

    private function createFeedItemGuid(
        string $subscriptionGuid,
        string $identifier,
    ): string {
        return substr(hash('sha256', $subscriptionGuid.$identifier), 0, 16);
    }
}
