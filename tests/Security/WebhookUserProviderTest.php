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
use App\Security\WebhookUserProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class WebhookUserProviderTest extends TestCase
{
    #[Test]
    public function loadUserByIdentifierReturnsUser(): void
    {
        $provider = new WebhookUserProvider('test_user', 'test_pass');

        $user = $provider->loadUserByIdentifier('test_user');

        $this->assertInstanceOf(WebhookUser::class, $user);
        $this->assertEquals('test_user', $user->getUserIdentifier());
        $this->assertEquals('test_pass', $user->getPassword());
    }

    #[Test]
    public function loadUserByIdentifierThrowsForWrongUser(): void
    {
        $provider = new WebhookUserProvider('test_user', 'test_pass');

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('wrong_user');
    }

    #[Test]
    public function loadUserByIdentifierThrowsForEmptyUser(): void
    {
        $provider = new WebhookUserProvider('', 'test_pass');

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('');
    }

    #[Test]
    public function refreshUserReturnsUser(): void
    {
        $provider = new WebhookUserProvider('test_user', 'test_pass');
        $user = new WebhookUser('test_user', 'test_pass');

        $refreshedUser = $provider->refreshUser($user);

        $this->assertInstanceOf(WebhookUser::class, $refreshedUser);
        $this->assertEquals('test_user', $refreshedUser->getUserIdentifier());
    }

    #[Test]
    public function refreshUserThrowsForUnsupportedUser(): void
    {
        $provider = new WebhookUserProvider('test_user', 'test_pass');
        $unsupportedUser = $this->createMock(
            \Symfony\Component\Security\Core\User\UserInterface::class,
        );

        $this->expectException(UnsupportedUserException::class);
        $provider->refreshUser($unsupportedUser);
    }

    #[Test]
    public function supportsClassReturnsTrueForWebhookUser(): void
    {
        $provider = new WebhookUserProvider('test_user', 'test_pass');

        $this->assertTrue($provider->supportsClass(WebhookUser::class));
    }

    #[Test]
    public function supportsClassReturnsFalseForOtherClasses(): void
    {
        $provider = new WebhookUserProvider('test_user', 'test_pass');

        $this->assertFalse($provider->supportsClass(\stdClass::class));
    }
}
