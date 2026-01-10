<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Twig;

use App\Service\StatusIndicator;
use App\Twig\StatusIndicatorExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class StatusIndicatorExtensionTest extends TestCase
{
    #[Test]
    public function getFunctionsReturnsStatusIsActiveFunction(): void
    {
        $statusIndicator = $this->createMock(StatusIndicator::class);
        $extension = new StatusIndicatorExtension($statusIndicator);

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertEquals('status_is_active', $functions[0]->getName());
    }

    #[Test]
    public function isActiveReturnsTrueWhenStatusIndicatorIsActive(): void
    {
        $statusIndicator = $this->createMock(StatusIndicator::class);
        $statusIndicator->method('isActive')->willReturn(true);

        $extension = new StatusIndicatorExtension($statusIndicator);

        $this->assertTrue($extension->isActive());
    }

    #[Test]
    public function isActiveReturnsFalseWhenStatusIndicatorIsNotActive(): void
    {
        $statusIndicator = $this->createMock(StatusIndicator::class);
        $statusIndicator->method('isActive')->willReturn(false);

        $extension = new StatusIndicatorExtension($statusIndicator);

        $this->assertFalse($extension->isActive());
    }
}
