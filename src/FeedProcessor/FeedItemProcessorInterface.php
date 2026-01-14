<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\FeedProcessor;

use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.feed_item_processor')]
interface FeedItemProcessorInterface
{
    #[Param(item: 'array<string, mixed>')]
    #[Returns('array<string, mixed>')]
    public function process(array $item): array;

    #[Param(item: 'array<string, mixed>')]
    public function supports(array $item): bool;

    public static function getPriority(): int;
}
