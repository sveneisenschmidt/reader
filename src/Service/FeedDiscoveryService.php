<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use FeedIo\FeedIo;
use PhpStaticAnalysis\Attributes\Returns;
use Psr\Log\LoggerInterface;

class FeedDiscoveryService
{
    public function __construct(
        private FeedIo $feedIo,
        private LoggerInterface $logger,
    ) {
    }

    #[Returns('array{feedUrl: string|null, error: string|null}')]
    public function resolveToFeedUrl(string $url): array
    {
        $url = $this->normalizeUrl($url);

        // First try to read as a direct feed
        try {
            $this->feedIo->read($url);

            return [
                'feedUrl' => $url,
                'error' => null,
            ];
        } catch (\Exception $e) {
            // Not a direct feed, try discovery
        }

        // Try to discover feeds from the webpage
        try {
            $feeds = $this->feedIo->discover($url);

            if (!empty($feeds)) {
                return [
                    'feedUrl' => $feeds[0],
                    'error' => null,
                ];
            }

            return [
                'feedUrl' => null,
                'error' => 'No RSS or Atom feed found on this website',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Feed discovery failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'feedUrl' => null,
                'error' => 'Could not fetch URL: '.$e->getMessage(),
            ];
        }
    }

    public function validateFeedUrl(string $url): ?string
    {
        $url = $this->normalizeUrl($url);

        try {
            $result = $this->feedIo->read($url);
            $feed = $result->getFeed();

            if ($feed->count() === 0) {
                return 'URL is not a valid RSS or Atom feed';
            }

            return null;
        } catch (\Exception $e) {
            return 'Could not fetch URL: '.$e->getMessage();
        }
    }

    public function normalizeUrl(string $url): string
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
}
