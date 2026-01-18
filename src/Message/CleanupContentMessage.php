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

class CleanupContentMessage implements RetainableMessageInterface, SourceAwareMessageInterface
{
    public function __construct(
        public readonly int $maxItemsPerSubscription = 50,
        private MessageSource $source = MessageSource::Webhook,
    ) {
    }

    public static function getRetentionLimit(): int
    {
        return 10;
    }

    public function getSource(): MessageSource
    {
        return $this->source;
    }
}
