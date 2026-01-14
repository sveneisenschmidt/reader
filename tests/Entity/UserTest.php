<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    #[Test]
    public function constructorSetsUsername(): void
    {
        $user = new User('testuser');

        $this->assertEquals('testuser', $user->getUsername());
    }

    #[Test]
    public function constructorSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User('testuser');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $user->getCreatedAt());
        $this->assertLessThanOrEqual($after, $user->getCreatedAt());
    }

    #[Test]
    public function getIdReturnsNullForNewUser(): void
    {
        $user = new User('testuser');

        $this->assertNull($user->getId());
    }

    #[Test]
    public function setUsernameUpdatesUsername(): void
    {
        $user = new User('original');
        $result = $user->setUsername('updated');

        $this->assertEquals('updated', $user->getUsername());
        $this->assertSame($user, $result);
    }

    #[Test]
    public function getUserIdentifierReturnsUsername(): void
    {
        $user = new User('testuser');

        $this->assertEquals('testuser', $user->getUserIdentifier());
    }

    #[Test]
    public function getRolesReturnsUserRole(): void
    {
        $user = new User('testuser');

        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    #[Test]
    public function emailIsNullByDefault(): void
    {
        $user = new User('testuser');

        $this->assertNull($user->getEmail());
    }

    #[Test]
    public function setEmailUpdatesEmail(): void
    {
        $user = new User('testuser');
        $result = $user->setEmail('test@example.com');

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertSame($user, $result);
    }

    #[Test]
    public function passwordIsNullByDefault(): void
    {
        $user = new User('testuser');

        $this->assertNull($user->getPassword());
    }

    #[Test]
    public function setPasswordUpdatesPassword(): void
    {
        $user = new User('testuser');
        $result = $user->setPassword('hashedpassword');

        $this->assertEquals('hashedpassword', $user->getPassword());
        $this->assertSame($user, $result);
    }

    #[Test]
    public function totpSecretIsNullByDefault(): void
    {
        $user = new User('testuser');

        $this->assertNull($user->getTotpSecret());
    }

    #[Test]
    public function setTotpSecretUpdatesTotpSecret(): void
    {
        $user = new User('testuser');
        $result = $user->setTotpSecret('JBSWY3DPEHPK3PXP');

        $this->assertEquals('JBSWY3DPEHPK3PXP', $user->getTotpSecret());
        $this->assertSame($user, $result);
    }

    #[Test]
    public function eraseCredentialsDoesNothing(): void
    {
        $user = new User('testuser');
        $user->setPassword('secret');

        $user->eraseCredentials();

        // Password should still be set (eraseCredentials is a no-op)
        $this->assertEquals('secret', $user->getPassword());
    }
}
