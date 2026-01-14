<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\FeedProcessor;

use App\FeedProcessor\FeedItemProcessorChain;
use App\FeedProcessor\FeedItemProcessorInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FeedItemProcessorChainTest extends TestCase
{
    #[Test]
    public function processItemsAppliesAllProcessors(): void
    {
        $processor1 = $this->createMock(FeedItemProcessorInterface::class);
        $processor1->method('supports')->willReturn(true);
        $processor1
            ->method('process')
            ->willReturnCallback(fn ($item) => array_merge(
                $item,
                ['processed1' => true],
            ));

        $processor2 = $this->createMock(FeedItemProcessorInterface::class);
        $processor2->method('supports')->willReturn(true);
        $processor2
            ->method('process')
            ->willReturnCallback(fn ($item) => array_merge(
                $item,
                ['processed2' => true],
            ));

        $chain = new FeedItemProcessorChain([$processor1, $processor2]);

        $items = [['title' => 'Test']];
        $result = $chain->processItems($items);

        $this->assertTrue($result[0]['processed1']);
        $this->assertTrue($result[0]['processed2']);
    }

    #[Test]
    public function processItemsSkipsNonSupportingProcessors(): void
    {
        $processor1 = $this->createMock(FeedItemProcessorInterface::class);
        $processor1->method('supports')->willReturn(false);
        $processor1->expects($this->never())->method('process');

        $processor2 = $this->createMock(FeedItemProcessorInterface::class);
        $processor2->method('supports')->willReturn(true);
        $processor2
            ->method('process')
            ->willReturnCallback(fn ($item) => array_merge(
                $item,
                ['processed' => true],
            ));

        $chain = new FeedItemProcessorChain([$processor1, $processor2]);

        $items = [['title' => 'Test']];
        $result = $chain->processItems($items);

        $this->assertTrue($result[0]['processed']);
    }

    #[Test]
    public function processItemsProcessesMultipleItems(): void
    {
        $processor = $this->createMock(FeedItemProcessorInterface::class);
        $processor->method('supports')->willReturn(true);
        $processor
            ->method('process')
            ->willReturnCallback(fn ($item) => array_merge(
                $item,
                ['processed' => true],
            ));

        $chain = new FeedItemProcessorChain([$processor]);

        $items = [
            ['title' => 'Item 1'],
            ['title' => 'Item 2'],
            ['title' => 'Item 3'],
        ];
        $result = $chain->processItems($items);

        $this->assertCount(3, $result);
        $this->assertTrue($result[0]['processed']);
        $this->assertTrue($result[1]['processed']);
        $this->assertTrue($result[2]['processed']);
    }

    #[Test]
    public function processItemsReturnsEmptyArrayForEmptyInput(): void
    {
        $chain = new FeedItemProcessorChain([]);

        $result = $chain->processItems([]);

        $this->assertEquals([], $result);
    }

    #[Test]
    public function processItemWorksWithNoProcessors(): void
    {
        $chain = new FeedItemProcessorChain([]);

        $item = ['title' => 'Test'];
        $result = $chain->processItem($item);

        $this->assertEquals($item, $result);
    }

    #[Test]
    public function processItemPassesResultThroughChain(): void
    {
        $processor1 = $this->createMock(FeedItemProcessorInterface::class);
        $processor1->method('supports')->willReturn(true);
        $processor1
            ->method('process')
            ->willReturnCallback(fn ($item) => array_merge(
                $item,
                ['step' => 1],
            ));

        $processor2 = $this->createMock(FeedItemProcessorInterface::class);
        $processor2->method('supports')->willReturn(true);
        $processor2
            ->method('process')
            ->willReturnCallback(function ($item) {
                $this->assertEquals(1, $item['step']);

                return array_merge($item, ['step' => 2]);
            });

        $chain = new FeedItemProcessorChain([$processor1, $processor2]);

        $result = $chain->processItem(['title' => 'Test']);

        $this->assertEquals(2, $result['step']);
    }
}
