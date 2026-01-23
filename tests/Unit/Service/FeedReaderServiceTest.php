<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\Feed\Processor\FeedItemProcessorChain;
use App\Domain\Feed\Service\FeedPersistenceService;
use App\Domain\Feed\Service\FeedReaderService;
use FeedIo\Feed;
use FeedIo\Feed\Item;
use FeedIo\FeedIo;
use FeedIo\Reader\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FeedReaderServiceTest extends TestCase
{
    #[Test]
    public function fetchFeedReturnsFeedData(): void
    {
        $item = new Item();
        $item->setLink('https://example.com/post1');
        $item->setPublicId('post-1');
        $item->setTitle('Post Title');
        $item->setSummary('Post summary');
        $item->setLastModified(new \DateTime('2024-01-01'));

        $feed = new Feed();
        $feed->setTitle('Test Feed');
        $feed->add($item);

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->expects($this->once())
            ->method('read')
            ->with('https://example.com/feed.xml')
            ->willReturn($result);

        $service = $this->createService($feedIo);

        $feedData = $service->fetchFeed('https://example.com/feed.xml');

        $this->assertEquals('Test Feed', $feedData['title']);
        $this->assertCount(1, $feedData['items']);
        $this->assertEquals('Post Title', $feedData['items'][0]['title']);
        $this->assertEquals(
            'https://example.com/post1',
            $feedData['items'][0]['link'],
        );
    }

    #[Test]
    public function fetchFeedUsesContentForExcerptIfNoSummary(): void
    {
        $item = new Item();
        $item->setLink('https://example.com/post1');
        $item->setPublicId('post-1');
        $item->setTitle('Post Title');
        $item->setContent('Full content here');

        $feed = new Feed();
        $feed->setTitle('Test Feed');
        $feed->add($item);

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $service = $this->createService($feedIo);

        $feedData = $service->fetchFeed('https://example.com/feed.xml');

        $this->assertEquals(
            'Full content here',
            $feedData['items'][0]['excerpt'],
        );
    }

    #[Test]
    public function fetchFeedReturnsEmptyTitleIfNotSet(): void
    {
        $item = new Item();
        $item->setLink('https://example.com/post1');
        $item->setPublicId('post-1');
        $item->setTitle('');
        $item->setSummary('This is the summary');

        $feed = new Feed();
        $feed->setTitle('Test Feed');
        $feed->add($item);

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $service = $this->createService($feedIo);

        $feedData = $service->fetchFeed('https://example.com/feed.xml');

        $this->assertEquals('', $feedData['items'][0]['title']);
    }

    #[Test]
    public function getFeedTitleReturnsTitle(): void
    {
        $feed = new Feed();
        $feed->setTitle('My Feed Title');

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $service = $this->createService($feedIo);

        $title = $service->getFeedTitle('https://example.com/feed.xml');

        $this->assertEquals('My Feed Title', $title);
    }

    #[Test]
    public function getFeedTitleReturnsEmptyStringIfNull(): void
    {
        $feed = new Feed();

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $service = $this->createService($feedIo);

        $title = $service->getFeedTitle('https://example.com/feed.xml');

        $this->assertEquals('', $title);
    }

    #[Test]
    public function fetchAndPersistFeedProcessesAndPersists(): void
    {
        $feed = new Feed();
        $feed->setTitle('Test Feed');

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $processorChain = $this->createMock(FeedItemProcessorChain::class);
        $processorChain
            ->expects($this->once())
            ->method('processItems')
            ->willReturn([]);

        $persistenceService = $this->createMock(FeedPersistenceService::class);
        $persistenceService
            ->expects($this->once())
            ->method('persistFeedItems')
            ->with([]);

        $service = $this->createService(
            $feedIo,
            $processorChain,
            $persistenceService,
        );

        $feedData = $service->fetchAndPersistFeed(
            'https://example.com/feed.xml',
        );

        $this->assertEquals('Test Feed', $feedData['title']);
        $this->assertArrayHasKey('items', $feedData);
        $this->assertIsArray($feedData['items']);
    }

    #[Test]
    public function fetchAndPersistFeedReturnsItems(): void
    {
        $item = new Item();
        $item->setLink('https://example.com/post1');
        $item->setPublicId('post-1');
        $item->setTitle('Post Title');
        $item->setSummary('Post summary');

        $feed = new Feed();
        $feed->setTitle('Test Feed');
        $feed->add($item);

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo->method('read')->willReturn($result);

        $processorChain = $this->createMock(FeedItemProcessorChain::class);
        $processorChain
            ->method('processItems')
            ->willReturnCallback(fn ($items) => $items);

        $persistenceService = $this->createMock(FeedPersistenceService::class);

        $service = $this->createService(
            $feedIo,
            $processorChain,
            $persistenceService,
        );

        $feedData = $service->fetchAndPersistFeed(
            'https://example.com/feed.xml',
        );

        $this->assertCount(1, $feedData['items']);
        $this->assertEquals('Post Title', $feedData['items'][0]['title']);
    }

    #[Test]
    public function refreshAllFeedsProcessesAllUrls(): void
    {
        $feed = new Feed();
        $feed->setTitle('Test Feed');

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->expects($this->exactly(2))
            ->method('read')
            ->willReturn($result);

        $processorChain = $this->createMock(FeedItemProcessorChain::class);
        $processorChain->method('processItems')->willReturn([]);

        $persistenceService = $this->createMock(FeedPersistenceService::class);
        $persistenceService
            ->expects($this->exactly(2))
            ->method('persistFeedItems');

        $service = $this->createService(
            $feedIo,
            $processorChain,
            $persistenceService,
        );

        $count = $service->refreshAllFeeds([
            'https://example.com/feed1.xml',
            'https://example.com/feed2.xml',
        ]);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function refreshAllFeedsContinuesOnError(): void
    {
        $feed = new Feed();
        $feed->setTitle('Test Feed');

        $result = $this->createMock(Result::class);
        $result->method('getFeed')->willReturn($feed);

        $feedIo = $this->createMock(FeedIo::class);
        $feedIo
            ->expects($this->exactly(2))
            ->method('read')
            ->willReturnCallback(function ($url) use ($result) {
                if ($url === 'https://example.com/feed1.xml') {
                    throw new \Exception('Connection failed');
                }

                return $result;
            });

        $processorChain = $this->createMock(FeedItemProcessorChain::class);
        $processorChain->method('processItems')->willReturn([]);

        $persistenceService = $this->createMock(FeedPersistenceService::class);
        $persistenceService->expects($this->once())->method('persistFeedItems');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to fetch feed', $this->anything());

        $service = $this->createService(
            $feedIo,
            $processorChain,
            $persistenceService,
            $logger,
        );

        $count = $service->refreshAllFeeds([
            'https://example.com/feed1.xml',
            'https://example.com/feed2.xml',
        ]);

        $this->assertEquals(0, $count);
    }

    private function createService(
        ?FeedIo $feedIo = null,
        ?FeedItemProcessorChain $processorChain = null,
        ?FeedPersistenceService $persistenceService = null,
        ?LoggerInterface $logger = null,
    ): FeedReaderService {
        return new FeedReaderService(
            $feedIo ?? $this->createStub(FeedIo::class),
            $processorChain ?? $this->createStub(FeedItemProcessorChain::class),
            $persistenceService ??
                $this->createStub(FeedPersistenceService::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
