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
    case Theme;
    case PullToRefresh;
    case AutoMarkRead;
    case KeyboardShortcuts;
    case FilterWords;

    public function value(): string
    {
        return match ($this) {
            self::Theme => 'auto',
            self::PullToRefresh => '1',
            self::AutoMarkRead => '0',
            self::KeyboardShortcuts => '0',
            self::FilterWords => '',
        };
    }

    public function asBool(): bool
    {
        return $this->value() === '1';
    }
}
