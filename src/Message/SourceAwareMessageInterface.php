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

interface SourceAwareMessageInterface
{
    public function getSource(): MessageSource;
}
