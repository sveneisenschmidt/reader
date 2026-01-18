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

/**
 * Tests for FeedViewController - handles feed view operations:
 * displaying feeds, filtering, refresh, and navigation.
 */
class FeedViewControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function indexPageLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("main#feed");
    }

    #[Test]
    public function indexPageShowsFeedSidebar(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("aside");
    }

    #[Test]
    public function indexPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();

        // Ensure a user exists but don't log in
        $this->ensureTestUserExists();

        $client->request("GET", "/");

        $this->assertResponseRedirects("/login");
    }

    #[Test]
    public function indexPageRedirectsToOnboardingWhenNoSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $this->deleteAllSubscriptionsForTestUser();

        $client->request("GET", "/");

        $this->assertResponseRedirects("/onboarding");
    }

    #[Test]
    public function refreshEndpointRequiresPostMethod(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/refresh");

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function refreshEndpointRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/refresh");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function refreshEndpointAcceptsPostWithCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/");
        $this->assertResponseIsSuccessful();

        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr("value");

        $client->request("POST", "/refresh", ["_token" => $token]);

        $this->assertResponseRedirects("/");
    }

    #[Test]
    public function refreshEndpointRedirectsToRefererWhenPresent(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/");
        $this->assertResponseIsSuccessful();

        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr("value");

        $client->request(
            "POST",
            "/refresh",
            ["_token" => $token],
            [],
            ["HTTP_REFERER" => "http://localhost/s/0123456789abcdef"],
        );

        $this->assertResponseRedirects("http://localhost/s/0123456789abcdef");
    }

    #[Test]
    public function refreshEndpointRedirectsToRefererWithQueryParams(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/");
        $this->assertResponseIsSuccessful();

        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr("value");

        $client->request(
            "POST",
            "/refresh",
            ["_token" => $token],
            [],
            ["HTTP_REFERER" => "http://localhost/s/0123456789abcdef?unread=1"],
        );

        $this->assertResponseRedirects(
            "http://localhost/s/0123456789abcdef?unread=1",
        );
    }

    #[Test]
    public function subscriptionRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/s/abc");
        $this->assertResponseStatusCodeSame(404);

        $client->request("GET", "/s/ghijklmnopqrstuv");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function subscriptionRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/s/0123456789abcdef");
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function feedItemRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/f/invalid");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function feedItemRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/f/0123456789abcdef");
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function filteredFeedItemRouteRequiresBothValidGuids(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/s/invalid/f/invalid");
        $this->assertResponseStatusCodeSame(404);

        $client->request("GET", "/s/0123456789abcdef/f/invalid");
        $this->assertResponseStatusCodeSame(404);

        $client->request("GET", "/s/invalid/f/0123456789abcdef");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function filteredFeedItemRouteWithValidGuidsLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/s/0123456789abcdef/f/fedcba9876543210");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageAcceptsUnreadQueryParam(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/?unread=1");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageAcceptsLimitQueryParam(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/?limit=50");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function feedItemPageShowsItemContent(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/f/fedcba9876543210");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("main#feed");
    }

    #[Test]
    public function filteredFeedItemWithItemLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $client->request("GET", "/s/0123456789abcdef/f/fedcba9876543210");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageWithUnreadFilterShowsOnlyUnread(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/?unread=1");

        $this->assertResponseIsSuccessful();
        // Unread filter is active - header actions exist
        $this->assertSelectorExists(".header-actions .icon-btn");
    }

    #[Test]
    public function indexPageDefaultShowsAllArticles(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/");

        $this->assertResponseIsSuccessful();
        // Unread filter toggle exists in header (first icon-btn), not active by default
        $unreadToggle = $crawler->filter(".header-actions .icon-btn")->first();
        $this->assertStringNotContainsString(
            "active",
            $unreadToggle->attr("class") ?? "",
        );
    }

    #[Test]
    public function bookmarksRouteRedirectsToIndexWhenNoBookmarks(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Ensure no bookmarks exist for the test item
        $bookmarkService = static::getContainer()->get(
            \App\Domain\ItemStatus\Service\BookmarkService::class,
        );
        $bookmarkService->unbookmark(
            $this->testUser->getId(),
            "fedcba9876543210",
        );

        $client->request("GET", "/bookmarks");

        $this->assertResponseRedirects("/");
    }

    #[Test]
    public function bookmarksRouteRedirectsToIndexWhenBookmarksDisabled(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Disable bookmarks in preferences
        $userPreferenceService = static::getContainer()->get(
            \App\Domain\User\Service\UserPreferenceService::class,
        );
        $userPreferenceService->setBookmarks($this->testUser->getId(), false);

        $client->request("GET", "/bookmarks");

        $this->assertResponseRedirects("/");
    }

    #[Test]
    public function bookmarksRouteShowsBookmarkedItems(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Ensure bookmarks are enabled
        $userPreferenceService = static::getContainer()->get(
            \App\Domain\User\Service\UserPreferenceService::class,
        );
        $userPreferenceService->setBookmarks($this->testUser->getId(), true);

        // Bookmark the test item
        $bookmarkService = static::getContainer()->get(
            \App\Domain\ItemStatus\Service\BookmarkService::class,
        );
        $bookmarkService->bookmark(
            $this->testUser->getId(),
            "fedcba9876543210",
        );

        $client->request("GET", "/bookmarks");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("main#feed");
    }
}
