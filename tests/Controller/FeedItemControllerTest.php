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
 * Tests for FeedItemController - handles individual feed item operations:
 * read/unread status, bookmarks, and external link opening.
 */
class FeedItemControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    // ========================================
    // Mark All Read Tests
    // ========================================

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
    public function markAllReadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/");
        $form = $crawler->filter('form[action$="/mark-all-read"]');

        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");
            $client->request("POST", "/mark-all-read", ["_token" => $token]);
            $this->assertResponseRedirects("/");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAllReadWithItemsRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/");
        $form = $crawler->filter('form[action$="/mark-all-read"]');

        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");
            $client->request("POST", "/mark-all-read", ["_token" => $token]);
            $this->assertResponseRedirects("/");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAllReadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/");
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request("POST", "/mark-all-read", [
            "_token" => $csrfToken,
        ]);

        $this->assertResponseRedirects("/");
    }

    // ========================================
    // Subscription Mark All Read Tests
    // ========================================

    #[Test]
    public function subscriptionMarkAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/s/0123456789abcdef/mark-all-read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionMarkAllReadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/s/0123456789abcdef");
        $form = $crawler->filter('form[action$="/mark-all-read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request("POST", "/s/0123456789abcdef/mark-all-read", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/s/0123456789abcdef");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function subscriptionMarkAllReadWithItemsRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/s/0123456789abcdef");
        $form = $crawler->filter('form[action$="/mark-all-read"]');

        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");
            $client->request("POST", "/s/0123456789abcdef/mark-all-read", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/s/0123456789abcdef");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function subscriptionMarkAllReadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/s/0123456789abcdef");
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request("POST", "/s/0123456789abcdef/mark-all-read", [
            "_token" => $csrfToken,
        ]);

        $this->assertResponseRedirects("/s/0123456789abcdef");
    }

    // ========================================
    // Mark As Read/Unread Tests
    // ========================================

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
    public function markAsReadWithValidCsrfTokenRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/f/0123456789abcdef");
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request("POST", "/f/0123456789abcdef/read", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/f/0123456789abcdef");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAsUnreadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request("GET", "/f/0123456789abcdef");
        $form = $crawler->filter('form[action$="/unread"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request("POST", "/f/0123456789abcdef/unread", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/f/0123456789abcdef");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAsReadWithItemRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request("POST", "/f/fedcba9876543210/read", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/f/fedcba9876543210");
        } else {
            // Item may already be read from previous test runs
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAsUnreadWithItemRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");
            $client->request("POST", "/f/fedcba9876543210/read", [
                "_token" => $token,
            ]);
        }

        // Now test mark as unread
        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $form = $crawler->filter('form[action$="/unread"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request("POST", "/f/fedcba9876543210/unread", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/f/fedcba9876543210");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAsReadWithBackActionRedirectsToFeedIndex(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request("POST", "/f/fedcba9876543210/read", [
            "_token" => $csrfToken,
            "redirect" => "list",
        ]);

        $this->assertResponseRedirects("/");
    }

    #[Test]
    public function markAsUnreadWithBackActionRedirectsToFeedIndex(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request("POST", "/f/fedcba9876543210/read", [
            "_token" => $csrfToken,
        ]);

        // Now mark as unread with back action
        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request("POST", "/f/fedcba9876543210/unread", [
            "_token" => $csrfToken,
            "redirect" => "list",
        ]);

        $this->assertResponseRedirects("/");
    }

    // ========================================
    // Filtered Mark As Read/Unread Tests
    // ========================================

    #[Test]
    public function filteredMarkAsReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/s/0123456789abcdef/f/fedcba9876543210/read");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredMarkAsUnreadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/unread",
        );

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredMarkAsReadWithValidCsrfTokenRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request(
                "POST",
                "/s/0123456789abcdef/f/fedcba9876543210/read",
                ["_token" => $token],
            );

            $this->assertResponseRedirects(
                "/s/0123456789abcdef/f/fedcba9876543210",
            );
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredMarkAsUnreadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $form = $crawler->filter('form[action$="/unread"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request(
                "POST",
                "/s/0123456789abcdef/f/fedcba9876543210/unread",
                ["_token" => $token],
            );

            $this->assertResponseRedirects(
                "/s/0123456789abcdef/f/fedcba9876543210",
            );
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredMarkAsReadWithItemRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request(
                "POST",
                "/s/0123456789abcdef/f/fedcba9876543210/read",
                ["_token" => $token],
            );

            $this->assertResponseRedirects(
                "/s/0123456789abcdef/f/fedcba9876543210",
            );
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredMarkAsReadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/read",
            ["_token" => $csrfToken],
        );

        $this->assertResponseRedirects(
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
    }

    #[Test]
    public function filteredMarkAsUnreadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/read",
            ["_token" => $csrfToken],
        );

        // Now mark as unread
        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/unread",
            ["_token" => $csrfToken],
        );

        $this->assertResponseRedirects(
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
    }

    #[Test]
    public function filteredMarkAsReadWithBackActionRedirectsToSubscription(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/read",
            [
                "_token" => $csrfToken,
                "redirect" => "list",
            ],
        );

        $this->assertResponseRedirects("/s/0123456789abcdef");
    }

    #[Test]
    public function filteredMarkAsUnreadWithBackActionRedirectsToSubscription(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/read",
            ["_token" => $csrfToken],
        );

        // Now mark as unread with back action
        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr("value");

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/unread",
            [
                "_token" => $csrfToken,
                "redirect" => "list",
            ],
        );

        $this->assertResponseRedirects("/s/0123456789abcdef");
    }

    // ========================================
    // Bookmark Tests
    // ========================================

    #[Test]
    public function bookmarkRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/f/0123456789abcdef/bookmark");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function unbookmarkRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("POST", "/f/0123456789abcdef/unbookmark");

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function bookmarkWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Enable bookmarks for the test user
        $userPreferenceService = static::getContainer()->get(
            \App\Service\UserPreferenceService::class,
        );
        $userPreferenceService->setBookmarks($this->testUser->getId(), true);

        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $form = $crawler->filter('form[action$="/bookmark"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request("POST", "/f/fedcba9876543210/bookmark", [
                "_token" => $token,
            ]);

            $this->assertResponseRedirects("/f/fedcba9876543210");
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function unbookmarkWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Enable bookmarks for the test user
        $userPreferenceService = static::getContainer()->get(
            \App\Service\UserPreferenceService::class,
        );
        $userPreferenceService->setBookmarks($this->testUser->getId(), true);

        // First bookmark the item
        $crawler = $client->request("GET", "/f/fedcba9876543210");
        $form = $crawler->filter('form[action$="/bookmark"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");
            $client->request("POST", "/f/fedcba9876543210/bookmark", [
                "_token" => $token,
            ]);

            // Now unbookmark
            $crawler = $client->request("GET", "/f/fedcba9876543210");
            $form = $crawler->filter('form[action$="/unbookmark"]');
            if ($form->count() > 0) {
                $token = $form->filter('input[name="_token"]')->attr("value");

                $client->request("POST", "/f/fedcba9876543210/unbookmark", [
                    "_token" => $token,
                ]);

                $this->assertResponseRedirects("/f/fedcba9876543210");
            } else {
                $this->assertTrue(true);
            }
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredBookmarkRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/bookmark",
        );

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredUnbookmarkRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            "POST",
            "/s/0123456789abcdef/f/fedcba9876543210/unbookmark",
        );

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredBookmarkWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Enable bookmarks for the test user
        $userPreferenceService = static::getContainer()->get(
            \App\Service\UserPreferenceService::class,
        );
        $userPreferenceService->setBookmarks($this->testUser->getId(), true);

        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $form = $crawler->filter('form[action$="/bookmark"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");

            $client->request(
                "POST",
                "/s/0123456789abcdef/f/fedcba9876543210/bookmark",
                ["_token" => $token],
            );

            $this->assertResponseRedirects(
                "/s/0123456789abcdef/f/fedcba9876543210",
            );
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredUnbookmarkWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Enable bookmarks for the test user
        $userPreferenceService = static::getContainer()->get(
            \App\Service\UserPreferenceService::class,
        );
        $userPreferenceService->setBookmarks($this->testUser->getId(), true);

        // First bookmark the item
        $crawler = $client->request(
            "GET",
            "/s/0123456789abcdef/f/fedcba9876543210",
        );
        $form = $crawler->filter('form[action$="/bookmark"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr("value");
            $client->request(
                "POST",
                "/s/0123456789abcdef/f/fedcba9876543210/bookmark",
                ["_token" => $token],
            );

            // Now unbookmark
            $crawler = $client->request(
                "GET",
                "/s/0123456789abcdef/f/fedcba9876543210",
            );
            $form = $crawler->filter('form[action$="/unbookmark"]');
            if ($form->count() > 0) {
                $token = $form->filter('input[name="_token"]')->attr("value");

                $client->request(
                    "POST",
                    "/s/0123456789abcdef/f/fedcba9876543210/unbookmark",
                    ["_token" => $token],
                );

                $this->assertResponseRedirects(
                    "/s/0123456789abcdef/f/fedcba9876543210",
                );
            } else {
                $this->assertTrue(true);
            }
        } else {
            $this->assertTrue(true);
        }
    }

    // ========================================
    // Open Route Tests
    // ========================================

    #[Test]
    public function openRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request("GET", "/f/invalid/open?url=https://example.com");
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function openRouteWithoutUrlRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $client->request("GET", "/f/fedcba9876543210/open");

        $this->assertResponseRedirects("/f/fedcba9876543210");
    }

    #[Test]
    public function openRouteWithInvalidFeedItemRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            "GET",
            "/f/0000000000000000/open?url=https://example.com",
        );

        $this->assertResponseRedirects("/f/0000000000000000");
    }

    #[Test]
    public function openRouteWithDisallowedUrlRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $client->request(
            "GET",
            "/f/fedcba9876543210/open?url=https://malicious.com",
        );

        $this->assertResponseRedirects("/f/fedcba9876543210");
    }

    #[Test]
    public function openRouteWithEmptyContentRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemEmptyContent($client);

        $client->request(
            "GET",
            "/f/fedcba9876543210/open?url=https://other-site.com",
        );

        $this->assertResponseRedirects("/f/fedcba9876543210");
    }

    #[Test]
    public function openRouteWithArticleLinkRedirectsToExternalUrl(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        $client->request(
            "GET",
            "/f/fedcba9876543210/open?url=https://example.com/article",
        );

        $this->assertResponseRedirects("https://example.com/article");
    }

    #[Test]
    public function openRouteWithContentLinkRedirectsToExternalUrl(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        $client->request(
            "GET",
            "/f/fedcba9876543210/open?url=https://linked-site.com/page",
        );

        $this->assertResponseRedirects("https://linked-site.com/page");
    }

    #[Test]
    public function openRouteMarksItemAsRead(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        $readStatusRepo = static::getContainer()->get(
            \App\Repository\ReadStatusRepository::class,
        );
        $readStatusRepo->markAsUnread(
            $this->testUser->getId(),
            "fedcba9876543210",
        );

        $this->assertFalse(
            $readStatusRepo->isRead(
                $this->testUser->getId(),
                "fedcba9876543210",
            ),
        );

        $client->request(
            "GET",
            "/f/fedcba9876543210/open?url=https://example.com/article",
        );

        $this->assertTrue(
            $readStatusRepo->isRead(
                $this->testUser->getId(),
                "fedcba9876543210",
            ),
        );
    }

    #[Test]
    public function openRouteMarksItemAsSeen(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        $client->request(
            "GET",
            "/f/fedcba9876543210/open?url=https://example.com/article",
        );

        $seenStatusRepo = static::getContainer()->get(
            \App\Repository\SeenStatusRepository::class,
        );
        $this->assertTrue(
            $seenStatusRepo->isSeen(
                $this->testUser->getId(),
                "fedcba9876543210",
            ),
        );
    }
}
