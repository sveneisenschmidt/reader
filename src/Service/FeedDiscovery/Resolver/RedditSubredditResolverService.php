<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service\FeedDiscovery\Resolver;

use App\Service\FeedDiscovery\FeedResolverInterface;
use App\Service\FeedDiscovery\FeedResolverResult;

final class RedditSubredditResolverService implements FeedResolverInterface
{
    private const RESOLVER_NAME = 'reddit-subreddit';

    public function supports(string $input): bool
    {
        $url = $this->normalizeUrl($input);
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || !preg_match('/(?:^|\.)(reddit\.com)$/i', $host)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return $this->isSubredditPath($path);
    }

    public function resolve(string $input): FeedResolverResult
    {
        $url = $this->normalizeUrl($input);
        $result = new FeedResolverResult(self::RESOLVER_NAME);

        $subreddit = $this->extractSubredditFromUrl($url);
        if ($subreddit === null) {
            return $result->setError('Could not determine subreddit from URL');
        }

        return $result->setFeedUrl($this->buildFeedUrl($subreddit));
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (
            !str_starts_with($url, 'http://')
            && !str_starts_with($url, 'https://')
        ) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    private function buildFeedUrl(string $subreddit): string
    {
        return sprintf(
            'https://www.reddit.com/r/%s/top.rss?t=week&limit=25',
            $subreddit,
        );
    }

    private function extractSubredditFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (preg_match('#^/r/([a-zA-Z0-9_]+)#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function isSubredditPath(string $path): bool
    {
        return (bool) preg_match('#^/r/[a-zA-Z0-9_]+#', $path);
    }
}
