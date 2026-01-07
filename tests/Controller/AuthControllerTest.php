<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Entity\Users\User;
use App\Repository\Users\UserRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends WebTestCase
{
    private function createTestUser(): void
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Check if test user already exists
        if ($userRepository->findByUsername("test@example.com")) {
            return;
        }

        $user = new User("test@example.com");
        $user->setEmail("test@example.com");
        $user->setPassword(
            $passwordHasher->hashPassword($user, "testpassword"),
        );
        $user->setTotpSecret("JBSWY3DPEHPK3PXP"); // Test secret
        $userRepository->save($user);
    }

    #[Test]
    public function setupPageLoads(): void
    {
        $client = static::createClient();
        $client->request("GET", "/setup");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("main#setup");
        $this->assertSelectorExists("form");
    }

    #[Test]
    public function setupPageShowsQrCode(): void
    {
        $client = static::createClient();
        $crawler = $client->request("GET", "/setup");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('figure img[alt="QR Code"]');
        $this->assertSelectorExists("figure figcaption code");
    }

    #[Test]
    public function setupPageHasRequiredFields(): void
    {
        $client = static::createClient();
        $crawler = $client->request("GET", "/setup");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="setup[email]"]');
        $this->assertSelectorExists('input[name="setup[password][first]"]');
        $this->assertSelectorExists('input[name="setup[password][second]"]');
        $this->assertSelectorExists(".otp-inputs");
        $this->assertSelectorExists('input[name="setup[otp]"]');
    }

    #[Test]
    public function setupFormValidatesEmptyFields(): void
    {
        $client = static::createClient();
        $crawler = $client->request("GET", "/setup");

        $form = $crawler->selectButton("Create Account")->form();
        $client->submit($form);

        // 422 is expected for validation errors
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists("main#setup");
    }

    #[Test]
    public function setupOtpInputComponentRendersCorrectly(): void
    {
        $client = static::createClient();
        $crawler = $client->request("GET", "/setup");

        $this->assertResponseIsSuccessful();

        // Check OTP input structure
        $otpInputs = $crawler->filter('.otp-inputs input[type="text"]');
        $this->assertEquals(
            6,
            $otpInputs->count(),
            "Should have 6 OTP input fields",
        );

        // Check hidden field for OTP value
        $this->assertSelectorExists('input[name="setup[otp]"][data-otp-value]');
    }

    #[Test]
    public function loginPageLoadsWhenUserExists(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request("GET", "/login");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("main#login");
        $this->assertSelectorExists("form");
    }

    #[Test]
    public function loginPageRedirectsToSetupWhenNoUser(): void
    {
        $client = static::createClient();

        // Clear users first
        $container = static::getContainer();
        $entityManager = $container->get("doctrine.orm.entity_manager");
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        $client->request("GET", "/login");

        $this->assertResponseRedirects("/setup");
    }

    #[Test]
    public function loginPageHasRequiredFields(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $crawler = $client->request("GET", "/login");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="login[email]"]');
        $this->assertSelectorExists('input[name="login[password]"]');
        $this->assertSelectorExists(".otp-inputs");
        $this->assertSelectorExists('input[name="login[otp]"]');
    }

    #[Test]
    public function loginPageShowsIntroText(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request("GET", "/login");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains("main#login > p", "Sign in");
    }

    #[Test]
    public function loginWithEmptyFieldsShowsError(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request("POST", "/login", [
            "login" => [
                "email" => "",
                "password" => "",
                "otp" => "",
                "_token" => "dummy",
            ],
        ]);

        $this->assertResponseRedirects("/login");
        $client->followRedirect();
        $this->assertSelectorExists('p[role="alert"]');
    }

    #[Test]
    public function loginWithInvalidCredentialsShowsError(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request("POST", "/login", [
            "login" => [
                "email" => "wrong@example.com",
                "password" => "wrongpassword",
                "otp" => "123456",
                "_token" => "dummy",
            ],
        ]);

        $this->assertResponseRedirects("/login");
        $client->followRedirect();
        $this->assertSelectorExists('p[role="alert"]');
    }

    #[Test]
    public function loginOtpInputComponentRendersCorrectly(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $crawler = $client->request("GET", "/login");

        $this->assertResponseIsSuccessful();

        // Check OTP input structure
        $otpInputs = $crawler->filter('.otp-inputs input[type="text"]');
        $this->assertEquals(
            6,
            $otpInputs->count(),
            "Should have 6 OTP input fields",
        );

        // Check hidden field for OTP value
        $this->assertSelectorExists('input[name="login[otp]"][data-otp-value]');
    }

    #[Test]
    public function logoutRouteExists(): void
    {
        $client = static::createClient();
        $client->request("GET", "/logout");

        // Should redirect (unauthenticated)
        $this->assertResponseRedirects();
    }
}
