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

class RefreshFeedsMessage implements RetainableMessageInterface, SourceAwareMessageInterface
{
    public function __construct(
        private MessageSource $source = MessageSource::Manual,
    ) {
    }

    public static function getRetentionLimit(): int
    {
        return 50;
    }

    public function getSource(): MessageSource
    {
        return $this->source;
    }
}
