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
use App\Service\EncryptionService;
use OTPHP\TOTP;
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
        $totpEncryption = $container->get(EncryptionService::class);

        // Check if test user already exists
        if ($userRepository->findByUsername('test@example.com')) {
            return;
        }

        $user = new User('test@example.com');
        $user->setEmail('test@example.com');
        $user->setPassword(
            $passwordHasher->hashPassword($user, 'testpassword'),
        );
        $user->setTotpSecret($totpEncryption->encrypt('JBSWY3DPEHPK3PXP'));
        $userRepository->save($user);
    }

    #[Test]
    public function setupPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/setup');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#setup');
        $this->assertSelectorExists('form');
    }

    #[Test]
    public function setupPageShowsQrCode(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('figure img[alt="QR Code"]');
        $this->assertSelectorExists('figure figcaption code');
    }

    #[Test]
    public function setupPageHasRequiredFields(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="setup[email]"]');
        $this->assertSelectorExists('input[name="setup[password][first]"]');
        $this->assertSelectorExists('input[name="setup[password][second]"]');
        $this->assertSelectorExists('input[name="setup[otp]"].otp-input');
    }

    #[Test]
    public function setupFormValidatesEmptyFields(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup');

        $form = $crawler->selectButton('Create Account')->form();
        $client->submit($form);

        // 422 is expected for validation errors
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('main#setup');
    }

    #[Test]
    public function setupOtpInputComponentRendersCorrectly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/setup');

        $this->assertResponseIsSuccessful();

        // Check OTP input - single field with otp-input class
        $otpInput = $crawler->filter('input[name="setup[otp]"].otp-input');
        $this->assertEquals(
            1,
            $otpInput->count(),
            'Should have OTP input field',
        );
        $this->assertEquals('6', $otpInput->attr('maxlength'));
        $this->assertEquals('numeric', $otpInput->attr('inputmode'));
    }

    #[Test]
    public function loginPageLoadsWhenUserExists(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#login');
        $this->assertSelectorExists('form');
    }

    #[Test]
    public function loginPageRedirectsToSetupWhenNoUser(): void
    {
        $client = static::createClient();

        // Clear users first
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        $client->request('GET', '/login');

        $this->assertResponseRedirects('/setup');
    }

    #[Test]
    public function loginPageHasRequiredFields(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="login[email]"]');
        $this->assertSelectorExists('input[name="login[password]"]');
        $this->assertSelectorExists('input[name="login[otp]"].otp-input');
    }

    #[Test]
    public function loginPageShowsIntroText(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main#login > p', 'Sign in');
    }

    #[Test]
    public function loginWithEmptyFieldsShowsError(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request('POST', '/login', [
            'login' => [
                'email' => '',
                'password' => '',
                'otp' => '',
                '_token' => 'dummy',
            ],
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('p[role="alert"]');
    }

    #[Test]
    public function loginWithInvalidCredentialsShowsError(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request('POST', '/login', [
            'login' => [
                'email' => 'wrong@example.com',
                'password' => 'wrongpassword',
                'otp' => '123456',
                '_token' => 'dummy',
            ],
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('p[role="alert"]');
    }

    #[Test]
    public function loginOtpInputComponentRendersCorrectly(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();

        // Check OTP input - single field with otp-input class
        $otpInput = $crawler->filter('input[name="login[otp]"].otp-input');
        $this->assertEquals(
            1,
            $otpInput->count(),
            'Should have OTP input field',
        );
        $this->assertEquals('6', $otpInput->attr('maxlength'));
        $this->assertEquals('numeric', $otpInput->attr('inputmode'));
    }

    #[Test]
    public function logoutRouteExists(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logout');

        // Should redirect (unauthenticated)
        $this->assertResponseRedirects();
    }

    #[Test]
    public function loginRedirectsToFeedWhenAlreadyLoggedIn(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findByUsername('test@example.com');

        $client->loginUser($user);
        $client->request('GET', '/login');

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function setupRedirectsToFeedWhenUserExists(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request('GET', '/setup');

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function setupFormWithInvalidOtpShowsError(): void
    {
        $client = static::createClient();

        // Clear users first to access setup
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        $crawler = $client->request('GET', '/setup');

        $form = $crawler->selectButton('Create Account')->form([
            'setup[email]' => 'newuser@example.com',
            'setup[password][first]' => 'securepassword123',
            'setup[password][second]' => 'securepassword123',
            'setup[otp]' => '000000', // Invalid OTP
        ]);

        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('p[role="alert"]');
        $this->assertSelectorTextContains('p[role="alert"]', 'Invalid OTP');
    }

    #[Test]
    public function setupFormWithPasswordMismatchShowsError(): void
    {
        $client = static::createClient();

        // Clear users first to access setup
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        $crawler = $client->request('GET', '/setup');

        $form = $crawler->selectButton('Create Account')->form([
            'setup[email]' => 'newuser@example.com',
            'setup[password][first]' => 'password1',
            'setup[password][second]' => 'password2', // Mismatch
            'setup[otp]' => '123456',
        ]);

        $client->submit($form);

        // 422 for validation error
        $this->assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function setupPreservesTotpSecretInSession(): void
    {
        $client = static::createClient();

        // Clear users first
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        // First request - get TOTP secret
        $crawler1 = $client->request('GET', '/setup');
        $secret1 = $crawler1->filter('figcaption code')->text();

        // Second request - should preserve the same secret
        $crawler2 = $client->request('GET', '/setup');
        $secret2 = $crawler2->filter('figcaption code')->text();

        $this->assertEquals($secret1, $secret2);
    }

    #[Test]
    public function loginWithCorrectPasswordButWrongOtpShowsError(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        $client->request('POST', '/login', [
            'login' => [
                'email' => 'test@example.com',
                'password' => 'testpassword',
                'otp' => '000000', // Wrong OTP
                '_token' => 'dummy',
            ],
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('p[role="alert"]');
    }

    #[Test]
    public function setupWithValidOtpCreatesUserAndRedirects(): void
    {
        $client = static::createClient();

        // Clear users first to access setup
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $entityManager
            ->createQuery("DELETE FROM App\Entity\Users\User")
            ->execute();

        // Load setup page and get the TOTP secret from the page
        $crawler = $client->request('GET', '/setup');
        $secret = $crawler->filter('figcaption code')->text();

        // Generate valid OTP using the secret shown on the page
        $totp = TOTP::createFromSecret($secret);
        $validOtp = $totp->now();

        $form = $crawler->selectButton('Create Account')->form([
            'setup[email]' => 'newuser@example.com',
            'setup[password][first]' => 'securepassword123',
            'setup[password][second]' => 'securepassword123',
            'setup[otp]' => $validOtp,
        ]);

        $client->submit($form);

        // Should redirect to login after successful setup
        $this->assertResponseRedirects('/login');

        // Verify user was created
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findByUsername('newuser@example.com');
        $this->assertNotNull($user);
        $this->assertEquals('newuser@example.com', $user->getEmail());
    }

    #[Test]
    public function loginRateLimiterBlocksTooManyAttempts(): void
    {
        $client = static::createClient();
        $this->createTestUser();

        // Clear rate limiter cache first
        $container = static::getContainer();
        $cache = $container->get('cache.rate_limiter');
        $cache->clear();

        // Make multiple failed login attempts to trigger rate limiter (limit is 20)
        for ($i = 0; $i < 21; ++$i) {
            $client->request('POST', '/login', [
                'login' => [
                    'email' => 'test@example.com',
                    'password' => 'wrongpassword',
                    'otp' => '123456',
                    '_token' => 'dummy',
                ],
            ]);
        }

        // The next attempt should be rate limited
        $client->request('POST', '/login', [
            'login' => [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
                'otp' => '123456',
                '_token' => 'dummy',
            ],
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('p[role="alert"]');
        $this->assertSelectorTextContains(
            'p[role="alert"]',
            'Too many login attempts',
        );
    }

    #[Test]
    public function loginWithValidCredentialsAndOtpRedirectsToFeed(): void
    {
        $client = static::createClient();

        // Clear rate limiter cache to avoid "too many attempts" error
        $container = static::getContainer();
        $cache = $container->get('cache.rate_limiter');
        $cache->clear();

        $this->createTestUser();

        // Generate valid OTP for the test user's secret
        $totp = TOTP::createFromSecret('JBSWY3DPEHPK3PXP');
        $validOtp = $totp->now();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            'login[email]' => 'test@example.com',
            'login[password]' => 'testpassword',
            'login[otp]' => $validOtp,
        ]);

        $client->submit($form);

        // Should redirect to feed after successful login
        $this->assertResponseRedirects('/');

        // Follow redirect - user is now authenticated
        // New users get redirected to /onboarding since they have no subscriptions
        $client->followRedirect();
        $this->assertResponseRedirects('/onboarding');
    }
}
