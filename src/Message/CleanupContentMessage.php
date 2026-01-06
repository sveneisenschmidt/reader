<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Message;

class CleanupContentMessage
{
    public function __construct(
        public readonly int $olderThanDays = 30,
    ) {}
}
