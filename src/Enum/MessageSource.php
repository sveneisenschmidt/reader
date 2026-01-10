<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Enum;

enum MessageSource: string
{
    case Worker = 'worker';
    case Webhook = 'webhook';
    case Manual = 'manual';
}
