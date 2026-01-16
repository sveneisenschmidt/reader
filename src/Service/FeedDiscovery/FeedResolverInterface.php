<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service\FeedDiscovery;

interface FeedResolverInterface
{
    public function supports(string $input): bool;

    public function resolve(string $input): FeedResolverResult;
}
