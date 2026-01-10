<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Repository\Subscriptions\SubscriptionRepository;
use App\Tests\Trait\AuthenticatedTestTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubscriptionControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function subscriptionsPageRedirectsWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request("GET", "/subscriptions");

        $this->assertResponseRedirects();
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
        $this->assertSelectorExists('input[name="subscriptions[new][url]"]');
    }

    #[Test]
    public function subscriptionsFormHasCsrfProtection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request("GET", "/subscriptions");

        $this->assertSelectorExists('input[name="subscriptions[_token]"]');
    }

    #[Test]
    public function subscriptionsFormHasSubscribeButton(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[name="subscriptions[add]"]');
    }

    #[Test]
    public function subscriptionsPageShowsExistingSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->createTestSubscription();

        $crawler = $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(".page-section--bordered");
        $this->assertSelectorExists('button[name="subscriptions[save]"]');
    }

    #[Test]
    public function subscriptionsPageShowsRemoveButtonForExistingFeeds(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->createTestSubscription();

        $crawler = $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(
            'button[name="subscriptions[existing][0][remove]"]',
        );
    }

    #[Test]
    public function addingNewFeedShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Subscribe")->form();
        $form["subscriptions[new][url]"] = "https://example.com/new-feed.xml";

        $client->submit($form);

        $this->assertResponseRedirects("/subscriptions");
        $client->followRedirect();

        $this->assertSelectorExists("p.flash-success");
        $this->assertSelectorTextContains("p.flash-success", "Feed added");
    }

    #[Test]
    public function addingDuplicateFeedShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Subscribe")->form();
        $form["subscriptions[new][url]"] = "https://example.com/feed.xml";

        $client->submit($form);

        $this->assertSelectorExists("p.form-error");
        $this->assertSelectorTextContains("p.form-error", "already subscribed");
    }

    #[Test]
    public function removingFeedShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Remove")->form();
        $client->submit($form);

        $this->assertResponseRedirects("/subscriptions");
        $client->followRedirect();

        $this->assertSelectorExists("p.flash-success");
        $this->assertSelectorTextContains("p.flash-success", "Feed removed");
    }

    #[Test]
    public function updatingFeedNameShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Update")->form();
        $form["subscriptions[existing][0][name]"] = "Updated Feed Name";

        $client->submit($form);

        $this->assertResponseRedirects("/subscriptions");
        $client->followRedirect();

        $this->assertSelectorExists("p.flash-success");
        $this->assertSelectorTextContains("p.flash-success", "Feed updated");
    }

    #[Test]
    public function existingSubscriptionShowsFeedUrl(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request("GET", "/subscriptions");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            ".subscription-row p a",
            "https://example.com/feed.xml",
        );
    }

    #[Test]
    public function addingNewFeedSetsRefreshTimestamp(): void
    {
        $client = static::createClient();
        $user = $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Subscribe")->form();
        $form["subscriptions[new][url]"] = "https://example.com/new-feed.xml";

        $client->submit($form);

        $this->assertResponseRedirects("/subscriptions");

        // Verify the subscription has a refresh timestamp
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );
        $subscriptions = $subscriptionRepository->findByUserId($user->getId());

        $this->assertCount(1, $subscriptions);
        $this->assertNotNull(
            $subscriptions[0]->getLastRefreshedAt(),
            "New subscription should have refresh timestamp set",
        );
    }

    #[Test]
    public function addingInvalidFeedShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request("GET", "/subscriptions");

        $form = $crawler->selectButton("Subscribe")->form();
        $form["subscriptions[new][url]"] =
            "https://example.com/invalid-feed.xml";

        $client->submit($form);

        $this->assertSelectorExists("p.form-error");
    }
}
