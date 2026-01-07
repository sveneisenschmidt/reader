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

class FeedControllerTest extends WebTestCase
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
        $client->request("GET", "/");

        $this->assertResponseRedirects("/login");
    }

    #[Test]
    public function indexPageRedirectsToOnboardingWhenNoSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        // Delete all subscriptions for this user using the trait method
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

        // Make a GET request first to establish the session
        $crawler = $client->request("GET", "/");
        $this->assertResponseIsSuccessful();

        // Get the CSRF token from the refresh form in the footer
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

        // Make a GET request first to establish the session
        $crawler = $client->request("GET", "/");
        $this->assertResponseIsSuccessful();

        // Get the CSRF token from the refresh form in the footer
        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr("value");

        // Send refresh request with a referer header pointing to a subscription page
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

        // Make a GET request first to establish the session
        $crawler = $client->request("GET", "/");
        $this->assertResponseIsSuccessful();

        // Get the CSRF token from the refresh form in the footer
        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr("value");

        // Send refresh request with a referer that includes query params
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
    public function markAllReadRequiresPostMethod(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/mark-all-read");

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function markAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/mark-all-read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Invalid GUID (too short)
        $client->request("GET", "/s/abc");
        $this->assertResponseStatusCodeSame(404);

        // Invalid GUID (non-hex characters)
        $client->request("GET", "/s/ghijklmnopqrstuv");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function subscriptionRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Valid 16-char hex GUID
        $client->request("GET", "/s/0123456789abcdef");
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function feedItemRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Invalid GUID
        $client->request("GET", "/f/invalid");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function feedItemRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Valid 16-char hex GUID
        $client->request("GET", "/f/0123456789abcdef");
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function markAsReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/f/0123456789abcdef/read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function markAsUnreadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/f/0123456789abcdef/unread");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionMarkAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/s/0123456789abcdef/mark-all-read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredFeedItemRouteRequiresBothValidGuids(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Both GUIDs invalid
        $client->request("GET", "/s/invalid/f/invalid");
        $this->assertResponseStatusCodeSame(404);

        // First GUID valid, second invalid
        $client->request("GET", "/s/0123456789abcdef/f/invalid");
        $this->assertResponseStatusCodeSame(404);

        // First GUID invalid, second valid
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
    public function markAsReadStayRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/f/0123456789abcdef/read-stay");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredMarkAsReadStayRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/read-stay",
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
