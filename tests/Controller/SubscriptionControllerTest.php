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

class SubscriptionControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function subscriptionsPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request("GET", "/subscriptions");

        $this->assertResponseRedirects("/login");
    }

    #[Test]
    public function subscriptionsPageLoads(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function subscriptionsPageShowsForm(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("form");
        $this->assertSelectorExists("textarea");
    }

    #[Test]
    public function subscriptionsFormSubmitRequiresValidYaml(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Save")->form();
        $form["subscriptions[yaml]"] = "invalid: yaml: syntax: [";

        $client->submit($form);

        // Should show page with error flash message
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("p.error");
    }

    #[Test]
    public function subscriptionsFormAcceptsValidYaml(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Save")->form();
        $form["subscriptions[yaml]"] =
            "- url: https://example.com/feed.xml\n  title: Test Feed\n";

        $client->submit($form);

        // Should redirect after successful save
        $this->assertResponseRedirects("/subscriptions");
    }

    #[Test]
    public function subscriptionsFormRejectsBlockedUrls(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Save")->form();
        $form["subscriptions[yaml]"] =
            "- url: http://localhost/feed.xml\n  title: Local Feed\n";

        $client->submit($form);

        // Should show page with error flash message
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("p.error");
    }

    #[Test]
    public function subscriptionsFormHasCsrfProtection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request("GET", "/subscriptions");

        // Check that form has a CSRF token field
        $this->assertSelectorExists('input[name="subscriptions[_token]"]');
    }

    #[Test]
    public function subscriptionsPageDisplaysYamlContent(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();

        // The textarea should exist
        $textarea = $crawler->filter('textarea[name="subscriptions[yaml]"]');
        $this->assertCount(1, $textarea);
    }
}
