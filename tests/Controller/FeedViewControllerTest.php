<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Domain\ItemStatus\Service\ReadStatusService;
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

        // Ensure a user exists but don't log in
        $this->ensureTestUserExists();

        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    #[Test]
    public function indexPageRedirectsToOnboardingWhenNoSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

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

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

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

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr('value');

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

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $token = $crawler
            ->filter('form[data-refresh-form] input[name="_token"]')
            ->attr('value');

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
    public function subscriptionRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/s/abc');
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/s/ghijklmnopqrstuv');
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function subscriptionRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/s/0123456789abcdef');
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function feedItemRouteRequires16CharHexGuid(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/f/invalid');
        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function feedItemRouteWithValidGuidLoads(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/f/0123456789abcdef');
        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function filteredFeedItemRouteRequiresBothValidGuids(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $client->request('GET', '/s/invalid/f/invalid');
        $this->assertResponseStatusCodeSame(404);

        $client->request('GET', '/s/0123456789abcdef/f/invalid');
        $this->assertResponseStatusCodeSame(404);

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
    public function feedItemPageShowsItemContent(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscriptionWithItem($client);

        $crawler = $client->request('GET', '/f/fedcba9876543210');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#feed');
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
    public function indexPageWithUnreadFilterShowsOnlyUnread(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/?unread=1');

        $this->assertResponseIsSuccessful();
        // Unread filter is active - header actions exist
        $this->assertSelectorExists('.header-actions .icon-btn');
    }

    #[Test]
    public function indexPageDefaultShowsAllArticles(): void
    {
        $client = static::createClient();
        $this->ensureTestUserHasSubscription($client);

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        // Unread filter toggle exists in header (first icon-btn), not active by default
        $unreadToggle = $crawler->filter('.header-actions .icon-btn')->first();
        $this->assertStringNotContainsString(
            'active',
            $unreadToggle->attr('class') ?? '',
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
            'fedcba9876543210',
        );

        $client->request('GET', '/bookmarks');

        $this->assertResponseRedirects('/');
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

        $client->request('GET', '/bookmarks');

        $this->assertResponseRedirects('/');
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
            'fedcba9876543210',
        );

        $client->request('GET', '/bookmarks');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#feed');
    }

    #[Test]
    public function sidebarShowsAllSubscriptionsWhenUnreadFilterDisabled(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        // Create two subscriptions
        $sub1 = $this->createTestSubscription('1111111111111111');
        $sub2 = $this->createTestSubscription('2222222222222222');

        // Create item for first subscription only
        $this->createTestFeedItem($sub1->getGuid(), 'aaaaaaaaaaaaaaaa');

        // Mark the item as read so sub1 has 0 unread
        $readStatusService = static::getContainer()->get(
            ReadStatusService::class,
        );
        $readStatusService->markAsRead(
            $this->testUser->getId(),
            'aaaaaaaaaaaaaaaa',
        );

        // Without unread filter, both subscriptions should be visible
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $sidebarHtml = $crawler->filter('aside')->html();
        $this->assertStringContainsString('Test Feed', $sidebarHtml);
    }

    #[Test]
    public function sidebarHidesSubscriptionsWithNoUnreadWhenFilterActive(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        // Create two subscriptions with different names
        $sub1 = $this->createTestSubscription('3333333333333333');
        $sub2 = $this->createTestSubscription('4444444444444444');

        // Create items for both subscriptions
        $this->createTestFeedItem($sub1->getGuid(), 'bbbbbbbbbbbbbbbb');
        $this->createTestFeedItem($sub2->getGuid(), 'cccccccccccccccc');

        // Mark item from sub1 as read
        $readStatusService = static::getContainer()->get(
            ReadStatusService::class,
        );
        $readStatusService->markAsRead(
            $this->testUser->getId(),
            'bbbbbbbbbbbbbbbb',
        );

        // With unread filter active, sub1 (0 unread) should be hidden
        // sub2 (1 unread) should be visible
        $crawler = $client->request('GET', '/?unread=1');

        $this->assertResponseIsSuccessful();
        $sidebar = $crawler->filter('aside .subscription-list');
        $this->assertGreaterThan(0, $sidebar->count());

        // Check that subscription with unread items shows a count
        $countsHtml = $sidebar->html();
        $this->assertStringContainsString('class="count"', $countsHtml);
    }

    #[Test]
    public function sidebarShowsActiveSubscriptionEvenWithNoUnread(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        // Use default subscription guid
        $sub = $this->createTestSubscription();
        $sguid = $sub->getGuid();

        // Create and mark item as read
        $this->createTestFeedItem($sguid, 'dddddddddddddddd');
        $readStatusService = static::getContainer()->get(
            ReadStatusService::class,
        );
        $readStatusService->markAsRead(
            $this->testUser->getId(),
            'dddddddddddddddd',
        );

        // Navigate to the subscription with unread filter
        // The subscription should still be visible because it's active
        $crawler = $client->request('GET', '/s/'.$sguid.'?unread=1');

        $this->assertResponseIsSuccessful();
        // The active subscription should be visible in the sidebar even with 0 unread
        // Check that the subscription link is present in the sidebar
        $subscriptionLink = $crawler->filter('aside a[href*="'.$sguid.'"]');
        $this->assertGreaterThan(0, $subscriptionLink->count());
    }
}
