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

class PreferencesControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function preferencesPageRedirectsWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/preferences');

        $this->assertResponseRedirects();
    }

    #[Test]
    public function preferencesPageLoads(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#preferences');
    }

    #[Test]
    public function preferencesPageShowsProfileForm(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="profile[username]"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    #[Test]
    public function preferencesPageShowsThemeForm(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(
            'input[name="preferences[theme]"][type="radio"]',
        );
    }

    #[Test]
    public function preferencesPageShowsEmail(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            'main#preferences',
            'test@example.com',
        );
    }

    #[Test]
    public function preferencesPageShowsStatusIndicator(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.status-indicator');
    }

    #[Test]
    public function preferencesPageShowsLogoutLink(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/logout"]');
    }

    #[Test]
    public function profileFormHasCsrfProtection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertSelectorExists('input[name="profile[_token]"]');
    }

    #[Test]
    public function preferencesFormHasCsrfProtection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertSelectorExists('input[name="preferences[_token]"]');
    }

    #[Test]
    public function submittingProfileFormUpdatesUsername(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/preferences');

        $form = $crawler->selectButton('profile[save]')->form();
        $form['profile[username]'] = 'test@example.com'; // Keep same as original

        $client->submit($form);

        $this->assertResponseRedirects('/preferences');

        $client->followRedirect();
        $this->assertSelectorExists('.flash-success');
    }

    #[Test]
    public function submittingThemeFormUpdatesTheme(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/preferences');

        $form = $crawler->selectButton('preferences[save]')->form();
        $form['preferences[theme]'] = 'auto'; // Keep same as default

        $client->submit($form);

        $this->assertResponseRedirects('/preferences');

        $client->followRedirect();
        $this->assertSelectorExists('.flash-success');
    }

    #[Test]
    public function submittingEmptyUsernameShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/preferences');

        $form = $crawler->selectButton('profile[save]')->form();
        $form['profile[username]'] = '';

        $client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorExists('.form-error');
    }

    #[Test]
    public function preferencesFormDisplaysUnreadOnlyCheckbox(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="preferences[unreadOnly]"]');
    }

    #[Test]
    public function preferencesFormSubmissionUpdatesUnreadOnly(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/preferences');

        $form = $crawler->selectButton('preferences[save]')->form();
        // Uncheck the unreadOnly checkbox (default is checked/true)
        $form['preferences[unreadOnly]'] = false;

        $client->submit($form);

        $this->assertResponseRedirects('/preferences');

        $client->followRedirect();
        $this->assertSelectorExists('.flash-success');
    }
}
