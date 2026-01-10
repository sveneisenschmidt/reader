<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Security;

use App\Security\WebhookUser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WebhookUserTest extends TestCase
{
    #[Test]
    public function getUserIdentifierReturnsUsername(): void
    {
        $user = new WebhookUser("webhook_user", "secret_pass");

        $this->assertEquals("webhook_user", $user->getUserIdentifier());
    }

    #[Test]
    public function getPasswordReturnsPassword(): void
    {
        $user = new WebhookUser("webhook_user", "secret_pass");

        $this->assertEquals("secret_pass", $user->getPassword());
    }

    #[Test]
    public function getRolesReturnsWebhookRole(): void
    {
        $user = new WebhookUser("webhook_user", "secret_pass");

        $this->assertEquals(["ROLE_WEBHOOK"], $user->getRoles());
    }

    #[Test]
    public function eraseCredentialsDoesNothing(): void
    {
        $user = new WebhookUser("webhook_user", "secret_pass");
        $user->eraseCredentials();

        // Should still have password after erasing
        $this->assertEquals("secret_pass", $user->getPassword());
    }
}
