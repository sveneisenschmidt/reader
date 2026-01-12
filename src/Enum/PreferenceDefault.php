<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Enum;

enum PreferenceDefault
{
    case ShowNextUnread;
    case PullToRefresh;
    case FilterWords;
    case UnreadOnly;

    public function value(): string
    {
        return match ($this) {
            self::ShowNextUnread => '0',
            self::PullToRefresh => '1',
            self::FilterWords => '',
            self::UnreadOnly => '1',
        };
    }

    public function asBool(): bool
    {
        return $this->value() === '1';
    }
}
