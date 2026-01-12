<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Tests\Trait\AuthenticatedTestTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OnboardingControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function onboardingPageRedirectsWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/onboarding');

        $this->assertResponseRedirects();
    }

    #[Test]
    public function onboardingPageLoadsWhenNoSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $client->request('GET', '/onboarding');

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function onboardingPageRedirectsWhenUserHasSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->createTestSubscription();

        $client->request('GET', '/onboarding');

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function onboardingPageShowsForm(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $client->request('GET', '/onboarding');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="first_feed[feedUrl]"]');
    }

    #[Test]
    public function onboardingFormHasCsrfProtection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $client->request('GET', '/onboarding');

        $this->assertSelectorExists('input[name="first_feed[_token]"]');
    }

    #[Test]
    public function submittingValidFeedUrlRedirectsToFeed(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/onboarding');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['first_feed[feedUrl]'] = 'https://example.com/feed.xml';

        $client->submit($form);

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function submittingInvalidFeedUrlShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/onboarding');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['first_feed[feedUrl]'] = 'not-a-valid-url';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error');
    }

    #[Test]
    public function submittingEmptyFeedUrlShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/onboarding');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['first_feed[feedUrl]'] = '';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error');
    }

    #[Test]
    public function submittingUrlWithInvalidFeedContentShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/onboarding');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['first_feed[feedUrl]'] = 'https://example.com/invalid-feed.xml';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error');
    }

    #[Test]
    public function submittingUrlWithExceptionShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/onboarding');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['first_feed[feedUrl]'] = 'https://example.com/exception-feed.xml';

        $client->submit($form);

        // Should not be a 500 error, but show a form error
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error');
        $this->assertSelectorTextContains('.form-error', 'Could not fetch URL');
    }
}
