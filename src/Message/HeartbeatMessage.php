<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Message;

class HeartbeatMessage implements RetainableMessageInterface
{
    public static function getRetentionLimit(): int
    {
        return 10;
    }
}
