<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service\FeedDiscovery\Resolver;

use App\Service\FeedDiscovery\Resolver\RedditSubredditResolverService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RedditSubredditResolverServiceTest extends TestCase
{
    #[Test]
    #[DataProvider('supportedUrlsProvider')]
    public function supportsRedditSubredditUrls(string $url, bool $expected): void
    {
        $resolver = new RedditSubredditResolverService();

        $this->assertEquals($expected, $resolver->supports($url));
    }

    public static function supportedUrlsProvider(): array
    {
        return [
            'subreddit path' => ['https://www.reddit.com/r/hackernews', true],
            'subreddit path without https' => ['reddit.com/r/php', true],
            'subreddit with trailing slash' => ['https://www.reddit.com/r/programming/', true],
            'subreddit with subpath' => ['https://www.reddit.com/r/technology/top', true],
            'old reddit' => ['https://old.reddit.com/r/linux', true],
            'new reddit' => ['https://new.reddit.com/r/webdev', true],
            'non-reddit domain' => ['https://example.com/r/test', false],
            'reddit without path' => ['https://www.reddit.com', false],
            'reddit home' => ['https://www.reddit.com/', false],
            'reddit user page' => ['https://www.reddit.com/user/someone', false],
            'reddit search' => ['https://www.reddit.com/search?q=test', false],
            'fake reddit domain' => ['https://notreddit.com/r/test', false],
            'fake subdomain' => ['https://reddit.com.evil.com/r/test', false],
            'random url' => ['https://example.com/feed.xml', false],
        ];
    }

    #[Test]
    public function resolvesSubredditUrl(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('https://www.reddit.com/r/hackernews');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('reddit-subreddit', $result->getResolverName());
        $this->assertEquals(
            'https://www.reddit.com/r/hackernews/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesSubredditWithTrailingSlash(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('https://www.reddit.com/r/programming/');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/programming/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesSubredditWithSubpath(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('https://www.reddit.com/r/technology/new');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/technology/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesUrlWithoutProtocol(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('reddit.com/r/php');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/php/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesUrlWithWhitespace(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('  https://www.reddit.com/r/webdev  ');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/webdev/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesOldReddit(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('https://old.reddit.com/r/linux');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/linux/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function handlesSubredditWithUnderscores(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('https://www.reddit.com/r/hacker_news');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/hacker_news/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function handlesSubredditWithNumbers(): void
    {
        $resolver = new RedditSubredditResolverService();

        $result = $resolver->resolve('https://www.reddit.com/r/php8');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.reddit.com/r/php8/top.rss?t=week&limit=25',
            $result->getFeedUrl(),
        );
    }
}
