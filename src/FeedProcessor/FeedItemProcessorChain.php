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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class FeedItemProcessorChain
{
    /** @var iterable<FeedItemProcessorInterface> */
    private iterable $processors;

    #[Param(processors: 'iterable<FeedItemProcessorInterface>')]
    public function __construct(
        #[
            AutowireIterator(
                'app.feed_item_processor',
                defaultPriorityMethod: 'getPriority',
            ),
        ]
        iterable $processors,
    ) {
        $this->processors = $processors;
    }

    #[Param(items: 'list<array<string, mixed>>')]
    #[Returns('list<array<string, mixed>>')]
    public function processItems(array $items): array
    {
        return array_map(fn (array $item) => $this->processItem($item), $items);
    }

    #[Param(item: 'array<string, mixed>')]
    #[Returns('array<string, mixed>')]
    public function processItem(array $item): array
    {
        foreach ($this->processors as $processor) {
            if ($processor->supports($item)) {
                $item = $processor->process($item);
            }
        }

        return $item;
    }
}
