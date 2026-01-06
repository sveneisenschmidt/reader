<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FeedControllerTest extends WebTestCase
{
    #[Test]
    public function indexPageLoads(): void
    {
        $client = static::createClient();
        $client->request("GET", "/");

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("main");
    }

    #[Test]
    public function indexPageShowsFeedList(): void
    {
        $client = static::createClient();
        $crawler = $client->request("GET", "/");

        $this->assertResponseIsSuccessful();
        // Check for feed sidebar structure
        $this->assertSelectorExists(".sidebar");
    }

    #[Test]
    public function refreshEndpointRequiresPostMethod(): void
    {
        $client = static::createClient();
        $client->request("GET", "/refresh");

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function refreshEndpointAcceptsPost(): void
    {
        $client = static::createClient();
        $client->request("POST", "/refresh");

        $this->assertResponseIsSuccessful();
        $this->assertEquals("OK", $client->getResponse()->getContent());
    }

    #[Test]
    public function markAllReadRequiresPostMethod(): void
    {
        $client = static::createClient();
        $client->request("GET", "/mark-all-read");

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function markAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request("POST", "/mark-all-read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();

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

        // Valid 16-char hex GUID
        $client->request("GET", "/s/0123456789abcdef");
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function feedItemRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();

        // Invalid GUID
        $client->request("GET", "/f/invalid");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function feedItemRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();

        // Valid 16-char hex GUID
        $client->request("GET", "/f/0123456789abcdef");
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function markAsReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request("POST", "/f/0123456789abcdef/read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function markAsUnreadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request("POST", "/f/0123456789abcdef/unread");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionMarkAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request("POST", "/s/0123456789abcdef/mark-all-read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredFeedItemRouteRequiresBothValidGuids(): void
    {
        $client = static::createClient();

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
        $client->request("GET", "/s/0123456789abcdef/f/fedcba9876543210");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageAcceptsUnreadQueryParam(): void
    {
        $client = static::createClient();
        $client->request("GET", "/?unread=1");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageAcceptsLimitQueryParam(): void
    {
        $client = static::createClient();
        $client->request("GET", "/?limit=50");

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function markAsReadStayRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request("POST", "/f/0123456789abcdef/read-stay");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredMarkAsReadStayRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/read-stay",
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
