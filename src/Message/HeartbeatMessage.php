<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Message;

use App\Enum\MessageSource;

class HeartbeatMessage implements RetainableMessageInterface, SourceAwareMessageInterface
{
    public static function getRetentionLimit(): int
    {
        return 10;
    }

    public function getSource(): MessageSource
    {
        return MessageSource::Worker;
    }
}
