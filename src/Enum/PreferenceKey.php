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
    case ShowNextUnread = 'show_next_unread';
    case PullToRefresh = 'pull_to_refresh';
    case FilterWords = 'filter_words';
    case UnreadOnly = 'unread_only';
}
