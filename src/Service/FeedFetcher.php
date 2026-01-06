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
    ) {}

    public function fetchAndPersistFeed(string $url): array
    {
        $response = $this->httpClient->request("GET", $url);
        $content = $response->getContent();
        $feedData = $this->parseFeed($content, $url);

        // Persist items to database
        $this->persistFeedItems($feedData["items"]);

        return $feedData;
    }

    private function persistFeedItems(array $items): void
    {
        $oneDayAgo = new \DateTimeImmutable("-24 hours");

        foreach ($items as $itemData) {
            $existing = $this->feedItemRepository->findByGuid(
                $itemData["guid"],
            );

            $publishedAt = \DateTimeImmutable::createFromMutable(
                $itemData["date"],
            );

            if ($existing === null) {
                $feedItem = new FeedItem(
                    $itemData["guid"],
                    $itemData["feedGuid"],
                    $itemData["title"],
                    $itemData["link"],
                    $itemData["source"],
                    $itemData["excerpt"],
                    $publishedAt,
                );
                $this->contentEntityManager->persist($feedItem);
            } elseif ($existing->getPublishedAt() > $oneDayAgo) {
                $existing->setTitle($itemData["title"]);
                $existing->setLink($itemData["link"]);
                $existing->setSource($itemData["source"]);
                $existing->setExcerpt($itemData["excerpt"]);
            }
        }

        $this->contentEntityManager->flush();
    }

    public function refreshAllFeeds(array $feedUrls): int
    {
        // Start all requests in parallel
        $responses = [];
        foreach ($feedUrls as $feedUrl) {
            $responses[$feedUrl] = $this->httpClient->request("GET", $feedUrl);
        }

        // Process responses as they complete
        $count = 0;
        foreach ($responses as $feedUrl => $response) {
            try {
                $content = $response->getContent();
                $feedData = $this->parseFeed($content, $feedUrl);
                $this->persistFeedItems($feedData["items"]);
                $count += count($feedData["items"]);
            } catch (\Exception $e) {
                $this->logger->error("Failed to fetch feed", [
                    "url" => $feedUrl,
                    "error" => $e->getMessage(),
                ]);
                continue;
            }
        }

        return $count;
    }

    public function getAllItems(array $feedGuids): array
    {
        $feedItems = $this->feedItemRepository->findByFeedGuids($feedGuids);
        return array_map(fn(FeedItem $item) => $item->toArray(), $feedItems);
    }

    public function getItemByGuid(string $guid): ?array
    {
        $feedItem = $this->feedItemRepository->findByGuid($guid);
        return $feedItem?->toArray();
    }

    private function parseFeed(string $content, string $feedUrl): array
    {
        $xml = @simplexml_load_string(
            $content,
            "SimpleXMLElement",
            LIBXML_NOCDATA,
        );

        if ($xml === false) {
            $this->logger->warning("Failed to parse feed XML", [
                "url" => $feedUrl,
            ]);
            return ["title" => "", "items" => []];
        }

        $feedGuid = $this->createGuid($feedUrl);

        // RSS 2.0
        if (isset($xml->channel)) {
            return $this->parseRss($xml, $feedUrl, $feedGuid);
        }

        // Atom
        if ($xml->getName() === "feed") {
            return $this->parseAtom($xml, $feedUrl, $feedGuid);
        }

        return ["title" => "", "items" => []];
    }

    private function parseRss(
        \SimpleXMLElement $xml,
        string $feedUrl,
        string $feedGuid,
    ): array {
        $channel = $xml->channel;
        $title = (string) $channel->title;
        $items = [];

        foreach ($channel->item as $item) {
            $link = (string) $item->link;
            $pubDate = (string) $item->pubDate;
            $excerpt = $this->cleanExcerpt((string) $item->description);
            $itemTitle = (string) $item->title;

            if (empty(trim($itemTitle))) {
                $itemTitle = $this->createTitleFromExcerpt($excerpt);
            }

            $items[] = [
                "guid" => $this->createGuid($link ?: (string) $item->guid),
                "title" => $itemTitle,
                "link" => $link,
                "source" => $title,
                "feedGuid" => $feedGuid,
                "date" => new \DateTime($pubDate ?: "now"),
                "excerpt" => $excerpt,
            ];
        }

        return ["title" => $title, "items" => $items];
    }

    private function parseAtom(
        \SimpleXMLElement $xml,
        string $feedUrl,
        string $feedGuid,
    ): array {
        $title = (string) $xml->title;
        $items = [];

        foreach ($xml->entry as $entry) {
            $link = "";
            foreach ($entry->link as $l) {
                if (
                    (string) $l["rel"] === "alternate" ||
                    (string) $l["rel"] === ""
                ) {
                    $link = (string) $l["href"];
                    break;
                }
            }

            $updated = (string) $entry->updated ?: (string) $entry->published;
            $excerpt = $this->cleanExcerpt(
                (string) ($entry->summary ?: $entry->content),
            );
            $itemTitle = (string) $entry->title;

            if (empty(trim($itemTitle))) {
                $itemTitle = $this->createTitleFromExcerpt($excerpt);
            }

            $items[] = [
                "guid" => $this->createGuid($link ?: (string) $entry->id),
                "title" => $itemTitle,
                "link" => $link,
                "source" => $title,
                "feedGuid" => $feedGuid,
                "date" => new \DateTime($updated ?: "now"),
                "excerpt" => $excerpt,
            ];
        }

        return ["title" => $title, "items" => $items];
    }

    public function createGuid(string $url): string
    {
        return substr(hash("sha256", $url), 0, 16);
    }

    private function cleanExcerpt(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, "UTF-8");
        $text = $this->feedContentSanitizer->sanitize($text);
        return trim($text);
    }

    private function createTitleFromExcerpt(string $excerpt): string
    {
        $text = strip_tags($excerpt);
        $text = trim($text);

        if (empty($text)) {
            return "Untitled";
        }

        if (mb_strlen($text) <= 50) {
            return $text;
        }

        return mb_substr($text, 0, 50) . "...";
    }

    public function getFeedTitle(string $url): string
    {
        $feedData = $this->fetchAndPersistFeed($url);
        return $feedData["title"];
    }

    public function getItemCountForFeed(string $feedGuid): int
    {
        return $this->feedItemRepository->getItemCountByFeedGuid($feedGuid);
    }
}
