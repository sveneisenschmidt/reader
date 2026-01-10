<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class WebhookUserProvider implements UserProviderInterface
{
    public function __construct(
        #[Autowire(env: 'WEBHOOK_USER')] private string $webhookUser,
        #[Autowire(env: 'WEBHOOK_PASSWORD')] private string $webhookPassword,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if ($identifier !== $this->webhookUser || $this->webhookUser === '') {
            throw new UserNotFoundException();
        }

        return new WebhookUser($this->webhookUser, $this->webhookPassword);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof WebhookUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === WebhookUser::class;
    }
}
