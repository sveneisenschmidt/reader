<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

use App\Domain\Feed\Service\SubscriptionService;
use App\Domain\User\Service\UserService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RefreshTimestampExtension extends AbstractExtension
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private UserService $userService,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('last_refresh', [$this, 'getLastRefresh'])];
    }

    public function getLastRefresh(): ?\DateTimeImmutable
    {
        $user = $this->userService->getCurrentUser();

        return $this->subscriptionService->getLatestRefreshTime($user->getId());
    }
}
