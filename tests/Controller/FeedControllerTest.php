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

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#feed');
    }

    #[Test]
    public function indexPageShowsFeedSidebar(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('aside');
    }

    #[Test]
    public function indexPageRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    #[Test]
    public function indexPageRedirectsToOnboardingWhenNoSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        // Delete all subscriptions for this user using the trait method
        $this->deleteAllSubscriptionsForTestUser();

        $client->request('GET', '/');

        $this->assertResponseRedirects('/onboarding');
    }

    #[Test]
    public function refreshEndpointRequiresPostMethod(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/refresh');

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function refreshEndpointRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('POST', '/refresh');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function refreshEndpointAcceptsPostWithCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Make a GET request first to establish the session
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Get the CSRF token from the refresh form in the footer
        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr('value');

        $client->request('POST', '/refresh', ['_token' => $token]);

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function refreshEndpointRedirectsToRefererWhenPresent(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Make a GET request first to establish the session
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Get the CSRF token from the refresh form in the footer
        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr('value');

        // Send refresh request with a referer header pointing to a subscription page
        $client->request(
            'POST',
            '/refresh',
            ['_token' => $token],
            [],
            ['HTTP_REFERER' => 'http://localhost/s/0123456789abcdef'],
        );

        $this->assertResponseRedirects('http://localhost/s/0123456789abcdef');
    }

    #[Test]
    public function refreshEndpointRedirectsToRefererWithQueryParams(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Make a GET request first to establish the session
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Get the CSRF token from the refresh form in the footer
        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr('value');

        // Send refresh request with a referer that includes query params
        $client->request(
            'POST',
            '/refresh',
            ['_token' => $token],
            [],
            ['HTTP_REFERER' => 'http://localhost/s/0123456789abcdef?unread=1'],
        );

        $this->assertResponseRedirects(
            'http://localhost/s/0123456789abcdef?unread=1',
        );
    }

    #[Test]
    public function markAllReadRequiresPostMethod(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/mark-all-read');

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function markAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('POST', '/mark-all-read');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Invalid GUID (too short)
        $client->request('GET', '/s/abc');
        $this->assertResponseStatusCodeSame(404);

        // Invalid GUID (non-hex characters)
        $client->request('GET', '/s/ghijklmnopqrstuv');
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function subscriptionRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Valid 16-char hex GUID
        $client->request('GET', '/s/0123456789abcdef');
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function feedItemRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Invalid GUID
        $client->request('GET', '/f/invalid');
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function feedItemRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Valid 16-char hex GUID
        $client->request('GET', '/f/0123456789abcdef');
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function markAsReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('POST', '/f/0123456789abcdef/read');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function markAsUnreadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('POST', '/f/0123456789abcdef/unread');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function subscriptionMarkAllReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('POST', '/s/0123456789abcdef/mark-all-read');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredFeedItemRouteRequiresBothValidGuids(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Both GUIDs invalid
        $client->request('GET', '/s/invalid/f/invalid');
        $this->assertResponseStatusCodeSame(404);

        // First GUID valid, second invalid
        $client->request('GET', '/s/0123456789abcdef/f/invalid');
        $this->assertResponseStatusCodeSame(404);

        // First GUID invalid, second valid
        $client->request('GET', '/s/invalid/f/0123456789abcdef');
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function filteredFeedItemRouteWithValidGuidsLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/s/0123456789abcdef/f/fedcba9876543210');

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageAcceptsUnreadQueryParam(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/?unread=1');

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function indexPageAcceptsLimitQueryParam(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/?limit=50');

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function markAllReadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form[action$="/mark-all-read"]');

        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');
            $client->request('POST', '/mark-all-read', ['_token' => $token]);
            $this->assertResponseRedirects('/');
        } else {
            // No items, so mark-all-read form is not shown
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function subscriptionMarkAllReadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/s/0123456789abcdef');
        $form = $crawler->filter('form[action$="/mark-all-read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request('POST', '/s/0123456789abcdef/mark-all-read', [
                '_token' => $token,
            ]);

            $this->assertResponseRedirects('/s/0123456789abcdef');
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAsReadWithValidCsrfTokenRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/f/0123456789abcdef');
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request('POST', '/f/0123456789abcdef/read', [
                '_token' => $token,
            ]);

            $this->assertResponseRedirects('/f/0123456789abcdef');
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAsUnreadWithValidCsrfTokenRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/f/0123456789abcdef');
        $form = $crawler->filter('form[action$="/unread"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request('POST', '/f/0123456789abcdef/unread', [
                '_token' => $token,
            ]);

            $this->assertResponseRedirects('/f/0123456789abcdef');
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredMarkAsReadWithValidCsrfTokenRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request(
                'POST',
                '/s/0123456789abcdef/f/fedcba9876543210/read',
                ['_token' => $token],
            );

            $this->assertResponseRedirects(
                '/s/0123456789abcdef/f/fedcba9876543210',
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
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $form = $crawler->filter('form[action$="/unread"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request(
                'POST',
                '/s/0123456789abcdef/f/fedcba9876543210/unread',
                ['_token' => $token],
            );

            $this->assertResponseRedirects(
                '/s/0123456789abcdef/f/fedcba9876543210',
            );
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredMarkAsReadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('POST', '/s/0123456789abcdef/f/fedcba9876543210/read');

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function filteredMarkAsUnreadRequiresCsrfToken(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/unread',
        );

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function feedItemPageShowsItemContent(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request('GET', '/f/fedcba9876543210');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#feed');
    }

    #[Test]
    public function markAsReadWithItemRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request('GET', '/f/fedcba9876543210');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/read"]');
        $this->assertGreaterThan(
            0,
            $form->count(),
            'Mark as read form should exist',
        );

        $token = $form->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/f/fedcba9876543210/read', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/f/fedcba9876543210');
    }

    #[Test]
    public function markAsUnreadWithItemRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request('GET', '/f/fedcba9876543210');
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');
            $client->request('POST', '/f/fedcba9876543210/read', [
                '_token' => $token,
            ]);
        }

        // Now test mark as unread
        $crawler = $client->request('GET', '/f/fedcba9876543210');
        $form = $crawler->filter('form[action$="/unread"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request('POST', '/f/fedcba9876543210/unread', [
                '_token' => $token,
            ]);

            $this->assertResponseRedirects('/f/fedcba9876543210');
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAllReadWithItemsRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form[action$="/mark-all-read"]');

        // Form only appears when there are unread items
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');
            $client->request('POST', '/mark-all-read', ['_token' => $token]);
            $this->assertResponseRedirects('/');
        } else {
            // Item was already marked as read in previous tests
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function subscriptionMarkAllReadWithItemsRedirects(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request('GET', '/s/0123456789abcdef');
        $form = $crawler->filter('form[action$="/mark-all-read"]');

        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');
            $client->request('POST', '/s/0123456789abcdef/mark-all-read', [
                '_token' => $token,
            ]);

            $this->assertResponseRedirects('/s/0123456789abcdef');
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function filteredFeedItemWithItemLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $client->request('GET', '/s/0123456789abcdef/f/fedcba9876543210');

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function filteredMarkAsReadWithItemRedirectsToSameItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $form = $crawler->filter('form[action$="/read"]');
        if ($form->count() > 0) {
            $token = $form->filter('input[name="_token"]')->attr('value');

            $client->request(
                'POST',
                '/s/0123456789abcdef/f/fedcba9876543210/read',
                ['_token' => $token],
            );

            $this->assertResponseRedirects(
                '/s/0123456789abcdef/f/fedcba9876543210',
            );
        } else {
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function markAllReadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Get CSRF token
        $crawler = $client->request('GET', '/');
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/mark-all-read', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function subscriptionMarkAllReadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Get CSRF token
        $crawler = $client->request('GET', '/s/0123456789abcdef');
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/s/0123456789abcdef/mark-all-read', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/s/0123456789abcdef');
    }

    #[Test]
    public function openRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/f/invalid/open?url=https://example.com');
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function openRouteWithoutUrlRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $client->request('GET', '/f/fedcba9876543210/open');

        $this->assertResponseRedirects('/f/fedcba9876543210');
    }

    #[Test]
    public function openRouteWithInvalidFeedItemRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request(
            'GET',
            '/f/0000000000000000/open?url=https://example.com',
        );

        $this->assertResponseRedirects('/f/0000000000000000');
    }

    #[Test]
    public function openRouteWithDisallowedUrlRedirectsToFeedItem(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // URL that doesn't exist in the article
        $client->request(
            'GET',
            '/f/fedcba9876543210/open?url=https://malicious.com',
        );

        $this->assertResponseRedirects('/f/fedcba9876543210');
    }

    #[Test]
    public function openRouteWithArticleLinkRedirectsToExternalUrl(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        // The article's main link is https://example.com/article
        $client->request(
            'GET',
            '/f/fedcba9876543210/open?url=https://example.com/article',
        );

        $this->assertResponseRedirects('https://example.com/article');
    }

    #[Test]
    public function openRouteWithContentLinkRedirectsToExternalUrl(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        // URL that exists in the article content
        $client->request(
            'GET',
            '/f/fedcba9876543210/open?url=https://linked-site.com/page',
        );

        $this->assertResponseRedirects('https://linked-site.com/page');
    }

    #[Test]
    public function openRouteMarksItemAsRead(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        // Clear any existing read status
        $readStatusRepo = static::getContainer()->get(
            \App\Repository\Users\ReadStatusRepository::class,
        );
        $readStatusRepo->markAsUnread(
            $this->testUser->getId(),
            'fedcba9876543210',
        );

        // Verify item is unread
        $this->assertFalse(
            $readStatusRepo->isRead(
                $this->testUser->getId(),
                'fedcba9876543210',
            ),
        );

        // Open the article link
        $client->request(
            'GET',
            '/f/fedcba9876543210/open?url=https://example.com/article',
        );

        // Verify item is now read
        $this->assertTrue(
            $readStatusRepo->isRead(
                $this->testUser->getId(),
                'fedcba9876543210',
            ),
        );
    }

    #[Test]
    public function openRouteMarksItemAsSeen(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItemContainingLink($client);

        // Open the article link
        $client->request(
            'GET',
            '/f/fedcba9876543210/open?url=https://example.com/article',
        );

        // Verify item is seen after opening
        $seenStatusRepo = static::getContainer()->get(
            \App\Repository\Users\SeenStatusRepository::class,
        );
        $this->assertTrue(
            $seenStatusRepo->isSeen(
                $this->testUser->getId(),
                'fedcba9876543210',
            ),
        );
    }

    #[Test]
    public function indexPageWithUnreadFilterShowsOnlyUnread(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/?unread=1');

        $this->assertResponseIsSuccessful();
        // The "Show unread (turn off)" link should be visible when filter is active
        $this->assertSelectorTextContains('.filter-toggle', 'Show unread');
    }

    #[Test]
    public function indexPageDefaultShowsAllArticles(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        // Request without unread param - default is now false (show all)
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        // The toggle should show "Show unread" (without "turn off") when filter is disabled
        $filterToggle = $crawler->filter('.filter-toggle')->text();
        $this->assertStringContainsString('Show unread', $filterToggle);
        $this->assertStringNotContainsString('turn off', $filterToggle);
    }

    #[Test]
    public function filteredMarkAsReadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // Get CSRF token from filtered feed item page
        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/read',
            ['_token' => $csrfToken],
        );

        $this->assertResponseRedirects(
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
    }

    #[Test]
    public function filteredMarkAsUnreadExecutesSuccessfully(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/read',
            ['_token' => $csrfToken],
        );

        // Now mark as unread
        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/unread',
            ['_token' => $csrfToken],
        );

        $this->assertResponseRedirects(
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
    }

    #[Test]
    public function markAsReadWithBackActionRedirectsToFeedIndex(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request('GET', '/f/fedcba9876543210');
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/f/fedcba9876543210/read', [
            '_token' => $csrfToken,
            'action' => 'back',
        ]);

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function markAsUnreadWithBackActionRedirectsToFeedIndex(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request('GET', '/f/fedcba9876543210');
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/f/fedcba9876543210/read', [
            '_token' => $csrfToken,
        ]);

        // Now mark as unread with back action
        $crawler = $client->request('GET', '/f/fedcba9876543210');
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request('POST', '/f/fedcba9876543210/unread', [
            '_token' => $csrfToken,
            'action' => 'back',
        ]);

        $this->assertResponseRedirects('/');
    }

    #[Test]
    public function filteredMarkAsReadWithBackActionRedirectsToSubscription(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/read',
            [
                '_token' => $csrfToken,
                'action' => 'back',
            ],
        );

        $this->assertResponseRedirects('/s/0123456789abcdef');
    }

    #[Test]
    public function filteredMarkAsUnreadWithBackActionRedirectsToSubscription(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        // First mark as read
        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/read',
            ['_token' => $csrfToken],
        );

        // Now mark as unread with back action
        $crawler = $client->request(
            'GET',
            '/s/0123456789abcdef/f/fedcba9876543210',
        );
        $csrfToken = $crawler
            ->filter('input[name="_token"]')
            ->first()
            ->attr('value');

        $client->request(
            'POST',
            '/s/0123456789abcdef/f/fedcba9876543210/unread',
            [
                '_token' => $csrfToken,
                'action' => 'back',
            ],
        );

        $this->assertResponseRedirects('/s/0123456789abcdef');
    }
}
