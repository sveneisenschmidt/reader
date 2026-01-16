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
use App\Service\FeedDiscoveryService;

final class FeedIoResolverService implements FeedResolverInterface
{
    private const RESOLVER_NAME = 'feed-io';

    public function __construct(
        private readonly FeedDiscoveryService $feedDiscoveryService,
    ) {
    }

    public function supports(string $input): bool
    {
        return true;
    }

    public function resolve(string $input): FeedResolverResult
    {
        $result = $this->feedDiscoveryService->resolveToFeedUrl($input);

        $resolverResult = new FeedResolverResult(self::RESOLVER_NAME);

        if ($result['error'] !== null) {
            return $resolverResult->setError($result['error']);
        }

        return $resolverResult->setFeedUrl($result['feedUrl']);
    }
}
