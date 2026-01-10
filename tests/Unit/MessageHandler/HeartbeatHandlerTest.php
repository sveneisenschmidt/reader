<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\MessageHandler;

use App\Message\HeartbeatMessage;
use App\MessageHandler\HeartbeatHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HeartbeatHandlerTest extends TestCase
{
    #[Test]
    public function handlesHeartbeatMessage(): void
    {
        $handler = new HeartbeatHandler();

        // Handler should complete without throwing exceptions
        // Actual tracking is done by ProcessedMessageMiddleware
        $handler(new HeartbeatMessage());

        $this->assertTrue(true);
    }
}
