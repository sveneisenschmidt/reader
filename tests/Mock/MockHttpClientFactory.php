<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;

class MockHttpClientFactory
{
    public function __invoke(
        string $method,
        string $url,
        array $options = [],
    ): MockResponse {
        // Return invalid content for URLs containing "invalid-feed"
        if (str_contains($url, 'invalid-feed')) {
            return new MockResponse('<html><body>Not a feed</body></html>', [
                'http_code' => 200,
                'response_headers' => ['Content-Type' => 'text/html'],
            ]);
        }

        // Return error for URLs containing "error-feed"
        if (str_contains($url, 'error-feed')) {
            return new MockResponse('', [
                'http_code' => 500,
                'response_headers' => [],
            ]);
        }

        // Throw exception for URLs containing "exception-feed"
        if (str_contains($url, 'exception-feed')) {
            throw new \RuntimeException('Simulated network error');
        }

        $fixturesPath = __DIR__.'/../Fixtures';
        $feedContent = file_get_contents($fixturesPath.'/valid-feed.xml');

        return new MockResponse($feedContent, [
            'http_code' => 200,
            'response_headers' => ['Content-Type' => 'application/rss+xml'],
        ]);
    }
}
