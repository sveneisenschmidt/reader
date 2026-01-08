<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class WebhookUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(
        private string $username,
        private string $password,
    ) {}

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return ["ROLE_WEBHOOK"];
    }

    public function eraseCredentials(): void {}
}
