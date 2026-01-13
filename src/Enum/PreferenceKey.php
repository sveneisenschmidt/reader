<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Enum;

enum PreferenceKey: string
{
    case Theme = 'theme';
    case PullToRefresh = 'pull_to_refresh';
    case AutoMarkRead = 'auto_mark_read';
    case KeyboardShortcuts = 'keyboard_shortcuts';
    case FilterWords = 'filter_words';
}
