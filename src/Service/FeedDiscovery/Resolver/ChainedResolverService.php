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

final class ChainedResolverService implements FeedResolverInterface
{
    /**
     * @var iterable<FeedResolverInterface>
     */
    private readonly iterable $resolvers;

    /**
     * @param iterable<FeedResolverInterface> $resolvers
     */
    public function __construct(iterable $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function supports(string $input): bool
    {
        return true;
    }

    public function resolve(string $input): FeedResolverResult
    {
        foreach ($this->resolvers as $resolver) {
            if (!$resolver->supports($input)) {
                continue;
            }

            $result = $resolver->resolve($input);

            if ($result->getStatus() === FeedResolverResult::STATUS_SUCCESS) {
                return $result;
            }

            if ($result->getStatus() === FeedResolverResult::STATUS_ERROR) {
                return $result;
            }
        }

        return new FeedResolverResult('chained')->setError(
            'Could not resolve feed URL',
        );
    }
}
