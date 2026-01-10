<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Content\FeedItem;
use App\Repository\Content\FeedItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeedFetcher
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private FeedItemRepository $feedItemRepository,
        private EntityManagerInterface $contentEntityManager,
        private HtmlSanitizerInterface $feedContentSanitizer,
        private LoggerInterface $logger,
        private FeedParser $feedParser,
    ) {
    }

    public function fetchAndPersistFeed(string $url): array
    {
        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();
        $feedData = $this->feedParser->parse($content, $url);

        $feedData['items'] = $this->sanitizeItems($feedData['items']);
        $this->persistFeedItems($feedData['items']);

        return $feedData;
    }

    private function sanitizeItems(array $items): array
    {
        return array_map(function ($item) {
            $item['excerpt'] = $this->cleanExcerpt($item['excerpt']);

            return $item;
        }, $items);
    }

    private function persistFeedItems(array $items): void
    {
        $twoDaysAgo = new \DateTimeImmutable('-48 hours');

        foreach ($items as $itemData) {
            $existing = $this->feedItemRepository->findByGuid(
                $itemData['guid'],
            );

            $publishedAt =
                $itemData['date'] instanceof \DateTimeImmutable
                    ? $itemData['date']
                    : \DateTimeImmutable::createFromMutable($itemData['date']);

            if ($existing === null) {
                $feedItem = new FeedItem(
                    $itemData['guid'],
                    $itemData['feedGuid'],
                    $itemData['title'],
                    $itemData['link'],
                    $itemData['source'],
                    $itemData['excerpt'],
                    $publishedAt,
                );
                $this->contentEntityManager->persist($feedItem);
            } elseif ($existing->getPublishedAt() > $twoDaysAgo) {
                $existing->setTitle($itemData['title']);
                $existing->setLink($itemData['link']);
                $existing->setSource($itemData['source']);
                $existing->setExcerpt($itemData['excerpt']);
            }
        }

        $this->contentEntityManager->flush();
    }

    public function refreshAllFeeds(array $feedUrls): int
    {
        $responses = [];
        foreach ($feedUrls as $feedUrl) {
            $responses[$feedUrl] = $this->httpClient->request('GET', $feedUrl);
        }

        $count = 0;
        foreach ($responses as $feedUrl => $response) {
            try {
                $content = $response->getContent();
                $feedData = $this->feedParser->parse($content, $feedUrl);
                $feedData['items'] = $this->sanitizeItems($feedData['items']);
                $this->persistFeedItems($feedData['items']);
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

    public function getAllItems(array $feedGuids): array
    {
        $feedItems = $this->feedItemRepository->findByFeedGuids($feedGuids);

        return array_map(fn (FeedItem $item) => $item->toArray(), $feedItems);
    }

    public function getItemByGuid(string $guid): ?array
    {
        $feedItem = $this->feedItemRepository->findByGuid($guid);

        return $feedItem?->toArray();
    }

    public function createGuid(string $url): string
    {
        return $this->feedParser->createGuid($url);
    }

    private function cleanExcerpt(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->feedContentSanitizer->sanitize($text);

        return trim($text);
    }

    public function getFeedTitle(string $url): string
    {
        $feedData = $this->fetchAndPersistFeed($url);

        return $feedData['title'];
    }

    public function getItemCountForFeed(string $feedGuid): int
    {
        return $this->feedItemRepository->getItemCountByFeedGuid($feedGuid);
    }

    public function validateFeedUrl(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
            ]);
            $content = $response->getContent();

            if (!$this->feedParser->isValid($content)) {
                return 'URL is not a valid RSS or Atom feed';
            }

            return null;
        } catch (\Exception $e) {
            return 'Could not fetch URL: '.$e->getMessage();
        }
    }
}
