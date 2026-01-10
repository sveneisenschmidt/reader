<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Entity\Content\FeedItem;
use App\Repository\Content\FeedItemRepository;
use App\Service\FeedFetcher;
use App\Service\FeedParser;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FeedFetcherTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private FeedItemRepository&MockObject $feedItemRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private HtmlSanitizerInterface&MockObject $sanitizer;
    private LoggerInterface&MockObject $logger;
    private FeedParser&MockObject $feedParser;
    private FeedFetcher $feedFetcher;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->feedItemRepository = $this->createMock(
            FeedItemRepository::class,
        );
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->feedParser = $this->createMock(FeedParser::class);

        $this->feedFetcher = new FeedFetcher(
            $this->httpClient,
            $this->feedItemRepository,
            $this->entityManager,
            $this->sanitizer,
            $this->logger,
            $this->feedParser,
        );
    }

    #[Test]
    public function fetchAndPersistFeedReturnsData(): void
    {
        $url = 'https://example.com/feed.xml';
        $content = '<rss>...</rss>';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($content);

        $this->httpClient
            ->method('request')
            ->with('GET', $url)
            ->willReturn($response);

        $feedData = [
            'title' => 'Test Feed',
            'items' => [
                [
                    'guid' => 'abc123',
                    'feedGuid' => 'feed123',
                    'title' => 'Test Item',
                    'link' => 'https://example.com/item',
                    'source' => 'Test Feed',
                    'excerpt' => '<p>Content</p>',
                    'date' => new \DateTimeImmutable(),
                ],
            ],
        ];

        $this->feedParser
            ->method('parse')
            ->with($content, $url)
            ->willReturn($feedData);

        $this->sanitizer
            ->method('sanitize')
            ->willReturnCallback(fn ($text) => strip_tags($text));

        $this->feedItemRepository->method('findByGuid')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->feedFetcher->fetchAndPersistFeed($url);

        $this->assertEquals('Test Feed', $result['title']);
        $this->assertCount(1, $result['items']);
    }

    #[Test]
    public function refreshAllFeedsProcessesMultipleFeeds(): void
    {
        $feedUrls = [
            'https://example.com/feed1.xml',
            'https://example.com/feed2.xml',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<rss>...</rss>');

        $this->httpClient->method('request')->willReturn($response);

        $feedData = [
            'title' => 'Test Feed',
            'items' => [
                [
                    'guid' => 'abc123',
                    'feedGuid' => 'feed123',
                    'title' => 'Test Item',
                    'link' => 'https://example.com/item',
                    'source' => 'Test Feed',
                    'excerpt' => 'Content',
                    'date' => new \DateTimeImmutable(),
                ],
            ],
        ];

        $this->feedParser->method('parse')->willReturn($feedData);
        $this->sanitizer->method('sanitize')->willReturnArgument(0);
        $this->feedItemRepository->method('findByGuid')->willReturn(null);

        $count = $this->feedFetcher->refreshAllFeeds($feedUrls);

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function refreshAllFeedsLogsErrorOnFailure(): void
    {
        $feedUrls = ['https://example.com/feed.xml'];

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getContent')
            ->willThrowException(new \Exception('Network error'));

        $this->httpClient->method('request')->willReturn($response);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to fetch feed', $this->anything());

        $count = $this->feedFetcher->refreshAllFeeds($feedUrls);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function getAllItemsReturnsItemArrays(): void
    {
        $feedGuids = ['guid1', 'guid2'];

        $feedItem = $this->createMock(FeedItem::class);
        $feedItem->method('toArray')->willReturn([
            'guid' => 'item1',
            'title' => 'Test',
        ]);

        $this->feedItemRepository
            ->method('findByFeedGuids')
            ->with($feedGuids)
            ->willReturn([$feedItem]);

        $result = $this->feedFetcher->getAllItems($feedGuids);

        $this->assertCount(1, $result);
        $this->assertEquals('item1', $result[0]['guid']);
    }

    #[Test]
    public function getItemByGuidReturnsItemArray(): void
    {
        $guid = 'abc123';

        $feedItem = $this->createMock(FeedItem::class);
        $feedItem->method('toArray')->willReturn([
            'guid' => $guid,
            'title' => 'Test',
        ]);

        $this->feedItemRepository
            ->method('findByGuid')
            ->with($guid)
            ->willReturn($feedItem);

        $result = $this->feedFetcher->getItemByGuid($guid);

        $this->assertEquals($guid, $result['guid']);
    }

    #[Test]
    public function getItemByGuidReturnsNullWhenNotFound(): void
    {
        $this->feedItemRepository->method('findByGuid')->willReturn(null);

        $result = $this->feedFetcher->getItemByGuid('nonexistent');

        $this->assertNull($result);
    }

    #[Test]
    public function createGuidDelegatesToParser(): void
    {
        $url = 'https://example.com/item';
        $expectedGuid = 'abc123def456';

        $this->feedParser
            ->method('createGuid')
            ->with($url)
            ->willReturn($expectedGuid);

        $result = $this->feedFetcher->createGuid($url);

        $this->assertEquals($expectedGuid, $result);
    }

    #[Test]
    public function getFeedTitleReturnsTitleFromFeed(): void
    {
        $url = 'https://example.com/feed.xml';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<rss>...</rss>');

        $this->httpClient->method('request')->willReturn($response);

        $this->feedParser->method('parse')->willReturn([
            'title' => 'My Feed Title',
            'items' => [],
        ]);

        $result = $this->feedFetcher->getFeedTitle($url);

        $this->assertEquals('My Feed Title', $result);
    }

    #[Test]
    public function getItemCountForFeedDelegatesToRepository(): void
    {
        $feedGuid = 'feed123';

        $this->feedItemRepository
            ->method('getItemCountByFeedGuid')
            ->with($feedGuid)
            ->willReturn(42);

        $result = $this->feedFetcher->getItemCountForFeed($feedGuid);

        $this->assertEquals(42, $result);
    }

    #[Test]
    public function validateFeedUrlReturnsNullForValidFeed(): void
    {
        $url = 'https://example.com/feed.xml';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<rss>...</rss>');

        $this->httpClient
            ->method('request')
            ->with('GET', $url, ['timeout' => 10])
            ->willReturn($response);

        $this->feedParser->method('isValid')->willReturn(true);

        $result = $this->feedFetcher->validateFeedUrl($url);

        $this->assertNull($result);
    }

    #[Test]
    public function validateFeedUrlReturnsErrorForInvalidFeed(): void
    {
        $url = 'https://example.com/not-a-feed';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<html>...</html>');

        $this->httpClient->method('request')->willReturn($response);
        $this->feedParser->method('isValid')->willReturn(false);

        $result = $this->feedFetcher->validateFeedUrl($url);

        $this->assertEquals('URL is not a valid RSS or Atom feed', $result);
    }

    #[Test]
    public function validateFeedUrlReturnsErrorOnException(): void
    {
        $url = 'https://example.com/feed.xml';

        $this->httpClient
            ->method('request')
            ->willThrowException(new \Exception('Connection refused'));

        $result = $this->feedFetcher->validateFeedUrl($url);

        $this->assertStringContainsString('Could not fetch URL', $result);
        $this->assertStringContainsString('Connection refused', $result);
    }

    #[Test]
    public function persistFeedItemsUpdatesExistingRecentItems(): void
    {
        $url = 'https://example.com/feed.xml';
        $content = '<rss>...</rss>';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($content);

        $this->httpClient->method('request')->willReturn($response);

        $existingItem = $this->createMock(FeedItem::class);
        $existingItem
            ->method('getPublishedAt')
            ->willReturn(new \DateTimeImmutable('-1 hour'));
        $existingItem->expects($this->once())->method('setTitle');
        $existingItem->expects($this->once())->method('setLink');
        $existingItem->expects($this->once())->method('setSource');
        $existingItem->expects($this->once())->method('setExcerpt');

        $feedData = [
            'title' => 'Test Feed',
            'items' => [
                [
                    'guid' => 'existing123',
                    'feedGuid' => 'feed123',
                    'title' => 'Updated Title',
                    'link' => 'https://example.com/item',
                    'source' => 'Test Feed',
                    'excerpt' => 'Content',
                    'date' => new \DateTimeImmutable(),
                ],
            ],
        ];

        $this->feedParser->method('parse')->willReturn($feedData);
        $this->sanitizer->method('sanitize')->willReturnArgument(0);
        $this->feedItemRepository
            ->method('findByGuid')
            ->willReturn($existingItem);

        $this->feedFetcher->fetchAndPersistFeed($url);
    }

    #[Test]
    public function persistFeedItemsDoesNotUpdateOldItems(): void
    {
        $url = 'https://example.com/feed.xml';
        $content = '<rss>...</rss>';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($content);

        $this->httpClient->method('request')->willReturn($response);

        $existingItem = $this->createMock(FeedItem::class);
        $existingItem
            ->method('getPublishedAt')
            ->willReturn(new \DateTimeImmutable('-1 week'));
        $existingItem->expects($this->never())->method('setTitle');

        $feedData = [
            'title' => 'Test Feed',
            'items' => [
                [
                    'guid' => 'old123',
                    'feedGuid' => 'feed123',
                    'title' => 'Updated Title',
                    'link' => 'https://example.com/item',
                    'source' => 'Test Feed',
                    'excerpt' => 'Content',
                    'date' => new \DateTimeImmutable(),
                ],
            ],
        ];

        $this->feedParser->method('parse')->willReturn($feedData);
        $this->sanitizer->method('sanitize')->willReturnArgument(0);
        $this->feedItemRepository
            ->method('findByGuid')
            ->willReturn($existingItem);

        $this->feedFetcher->fetchAndPersistFeed($url);
    }

    #[Test]
    public function fetchAndPersistFeedHandlesMutableDateTime(): void
    {
        $url = 'https://example.com/feed.xml';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('<rss>...</rss>');

        $this->httpClient->method('request')->willReturn($response);

        // Use mutable DateTime instead of DateTimeImmutable
        $feedData = [
            'title' => 'Test Feed',
            'items' => [
                [
                    'guid' => 'abc123',
                    'feedGuid' => 'feed123',
                    'title' => 'Test Item',
                    'link' => 'https://example.com/item',
                    'source' => 'Test Feed',
                    'excerpt' => 'Content',
                    'date' => new \DateTime(),
                ],
            ],
        ];

        $this->feedParser->method('parse')->willReturn($feedData);
        $this->sanitizer->method('sanitize')->willReturnArgument(0);
        $this->feedItemRepository->method('findByGuid')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');

        $this->feedFetcher->fetchAndPersistFeed($url);
    }
}
