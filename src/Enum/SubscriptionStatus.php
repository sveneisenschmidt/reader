<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Enum;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Unreachable = 'unreachable';
    case Invalid = 'invalid';
    case Timeout = 'timeout';
}
