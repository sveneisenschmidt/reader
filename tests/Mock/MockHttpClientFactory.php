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
        $fixturesPath = __DIR__ . "/../Fixtures";
        $feedContent = file_get_contents($fixturesPath . "/valid-feed.xml");

        return new MockResponse($feedContent, [
            "http_code" => 200,
            "response_headers" => ["Content-Type" => "application/rss+xml"],
        ]);
    }
}
