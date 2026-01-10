<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Security;

use App\Security\AppAuthenticator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class AppAuthenticatorTest extends KernelTestCase
{
    private AppAuthenticator $authenticator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->authenticator = $container->get(AppAuthenticator::class);
    }

    #[Test]
    public function supportsReturnsTrueForLoginPost(): void
    {
        $request = Request::create("/login", "POST");

        $this->assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForLoginGet(): void
    {
        $request = Request::create("/login", "GET");

        $this->assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForOtherPaths(): void
    {
        $request = Request::create("/other", "POST");

        $this->assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForOtherMethods(): void
    {
        $request = Request::create("/login", "PUT");

        $this->assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function onAuthenticationSuccessRedirectsToFeedIndex(): void
    {
        $request = Request::create("/login", "POST");
        $user = $this->createMock(
            \Symfony\Component\Security\Core\User\UserInterface::class,
        );
        $user->method("getRoles")->willReturn(["ROLE_USER"]);
        $user->method("getUserIdentifier")->willReturn("testuser");

        $token = new UsernamePasswordToken($user, "main", ["ROLE_USER"]);

        $response = $this->authenticator->onAuthenticationSuccess(
            $request,
            $token,
            "main",
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString("/", $response->getTargetUrl());
    }

    #[Test]
    public function onAuthenticationFailureRedirectsToLogin(): void
    {
        $request = Request::create("/login", "POST");
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $exception = new CustomUserMessageAuthenticationException(
            "Invalid credentials.",
        );

        $response = $this->authenticator->onAuthenticationFailure(
            $request,
            $exception,
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString("/login", $response->getTargetUrl());
        $this->assertEquals(
            "Invalid credentials.",
            $session->get("auth_error"),
        );
    }

    #[Test]
    public function startRedirectsToLoginWhenUserExists(): void
    {
        // Ensure a user exists
        $container = static::getContainer();
        $userRepository = $container->get(
            \App\Repository\Users\UserRepository::class,
        );
        if (!$userRepository->hasAnyUser()) {
            $user = new \App\Entity\Users\User("test@example.com");
            $user->setEmail("test@example.com");
            $user->setPassword("hashedpassword");
            $userRepository->save($user);
        }

        $request = Request::create("/protected", "GET");

        $response = $this->authenticator->start($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString("/login", $response->getTargetUrl());
    }

    #[Test]
    public function startRedirectsToSetupWhenNoUserExists(): void
    {
        // Clear all users
        $container = static::getContainer();
        $entityManager = $container->get("doctrine.orm.entity_manager");
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        $request = Request::create("/protected", "GET");

        $response = $this->authenticator->start($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString("/setup", $response->getTargetUrl());
    }
}
