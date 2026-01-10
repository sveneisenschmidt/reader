<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

use App\Service\StatusIndicator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StatusIndicatorExtension extends AbstractExtension
{
    public function __construct(
        private StatusIndicator $statusIndicator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('status_is_active', [$this, 'isActive']),
        ];
    }

    public function isActive(): bool
    {
        return $this->statusIndicator->isActive();
    }
}
