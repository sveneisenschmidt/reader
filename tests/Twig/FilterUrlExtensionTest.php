<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Twig;

use App\Entity\Users\User;
use App\Service\UserPreferenceService;
use App\Service\UserService;
use App\Twig\FilterUrlExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FilterUrlExtensionTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private UserService&MockObject $userService;
    private UserPreferenceService&MockObject $userPreferenceService;
    private FilterUrlExtension $extension;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->userService = $this->createMock(UserService::class);
        $this->userService->method('getCurrentUserOrNull')->willReturn($user);

        $this->userPreferenceService = $this->createMock(
            UserPreferenceService::class,
        );
        // Default: unreadOnly is enabled (true)
        $this->userPreferenceService
            ->method('isUnreadOnlyEnabled')
            ->willReturn(true);

        $this->extension = new FilterUrlExtension(
            $this->requestStack,
            $this->urlGenerator,
            $this->userService,
            $this->userPreferenceService,
        );
    }

    private function createMockRequest(
        string $route = 'feed_index',
        array $routeParams = [],
        array $queryParams = [],
    ): Request {
        $request = Request::create('/', 'GET', $queryParams);
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', $routeParams);

        return $request;
    }

    #[Test]
    public function getFunctionsReturnsExpectedFunctions(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(2, $functions);

        $names = array_map(fn ($f) => $f->getName(), $functions);
        $this->assertContains('filter_url', $names);
        $this->assertContains('path_with_filters', $names);
    }

    #[Test]
    public function filterUrlGeneratesUrlWithCurrentFilters(): void
    {
        // With default unreadOnly=true, unread=0 is non-default and should be preserved
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['unread' => '0'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['unread' => '0'])
            ->willReturn('/?unread=0');

        $result = $this->extension->filterUrl();

        $this->assertEquals('/?unread=0', $result);
    }

    #[Test]
    public function filterUrlMergesNewParams(): void
    {
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['unread' => '0'])
            ->willReturn('/?unread=0');

        $result = $this->extension->filterUrl(['unread' => '0']);

        $this->assertEquals('/?unread=0', $result);
    }

    #[Test]
    public function filterUrlRemovesDefaultValues(): void
    {
        // With default unreadOnly=true, unread=1 is the default and should be removed
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', [])
            ->willReturn('/');

        $result = $this->extension->filterUrl(['unread' => '1', 'limit' => 50]);

        $this->assertEquals('/', $result);
    }

    #[Test]
    public function filterUrlPreservesRouteParams(): void
    {
        $request = $this->createMockRequest('subscription_show', [
            'sguid' => 'abc123',
        ]);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('subscription_show', ['sguid' => 'abc123', 'unread' => '0'])
            ->willReturn('/s/abc123?unread=0');

        $result = $this->extension->filterUrl(['unread' => '0']);

        $this->assertEquals('/s/abc123?unread=0', $result);
    }

    #[Test]
    public function filterUrlPreservesNonDefaultLimit(): void
    {
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['limit' => '100'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['limit' => 100])
            ->willReturn('/?limit=100');

        $result = $this->extension->filterUrl();

        $this->assertEquals('/?limit=100', $result);
    }

    #[Test]
    public function pathWithFiltersGeneratesPathWithCurrentFilters(): void
    {
        // With default unreadOnly=true, unread=0 is non-default and should be preserved
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['unread' => '0'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item', ['fguid' => 'abc123', 'unread' => '0'])
            ->willReturn('/f/abc123?unread=0');

        $result = $this->extension->pathWithFilters('feed_item', [
            'fguid' => 'abc123',
        ]);

        $this->assertEquals('/f/abc123?unread=0', $result);
    }

    #[Test]
    public function pathWithFiltersDoesNotIncludeUnreadWhenDefault(): void
    {
        // With default unreadOnly=true, no unread param means default=true, so no param needed
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item', ['fguid' => 'abc123'])
            ->willReturn('/f/abc123');

        $result = $this->extension->pathWithFilters('feed_item', [
            'fguid' => 'abc123',
        ]);

        $this->assertEquals('/f/abc123', $result);
    }

    #[Test]
    public function pathWithFiltersIncludesNonDefaultLimit(): void
    {
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['limit' => '25'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item', ['fguid' => 'abc123', 'limit' => 25])
            ->willReturn('/f/abc123?limit=25');

        $result = $this->extension->pathWithFilters('feed_item', [
            'fguid' => 'abc123',
        ]);

        $this->assertEquals('/f/abc123?limit=25', $result);
    }

    #[Test]
    public function pathWithFiltersDoesNotIncludeDefaultLimit(): void
    {
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['limit' => '50'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item', ['fguid' => 'abc123'])
            ->willReturn('/f/abc123');

        $result = $this->extension->pathWithFilters('feed_item', [
            'fguid' => 'abc123',
        ]);

        $this->assertEquals('/f/abc123', $result);
    }

    #[Test]
    public function filterUrlHandlesZeroAsIntegerForUnread(): void
    {
        // With default unreadOnly=true, unread=0 is non-default
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['unread' => '0'])
            ->willReturn('/?unread=0');

        $result = $this->extension->filterUrl(['unread' => 0]);

        $this->assertEquals('/?unread=0', $result);
    }

    #[Test]
    public function filterUrlRespectsUserPreferenceWhenDisabled(): void
    {
        // Create extension with unreadOnly preference disabled
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $userService = $this->createMock(UserService::class);
        $userService->method('getCurrentUserOrNull')->willReturn($user);

        $userPreferenceService = $this->createMock(
            UserPreferenceService::class,
        );
        // unreadOnly is disabled (false)
        $userPreferenceService
            ->method('isUnreadOnlyEnabled')
            ->willReturn(false);

        $extension = new FilterUrlExtension(
            $this->requestStack,
            $this->urlGenerator,
            $userService,
            $userPreferenceService,
        );

        // With default unreadOnly=false, unread=1 is non-default
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['unread' => '1'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['unread' => '1'])
            ->willReturn('/?unread=1');

        $result = $extension->filterUrl();

        $this->assertEquals('/?unread=1', $result);
    }

    #[Test]
    public function filterUrlFallsBackToDefaultWhenNoUserLoggedIn(): void
    {
        // Create extension with no user logged in
        $userService = $this->createMock(UserService::class);
        $userService->method('getCurrentUserOrNull')->willReturn(null);

        $extension = new FilterUrlExtension(
            $this->requestStack,
            $this->urlGenerator,
            $userService,
            $this->userPreferenceService,
        );

        // With no user, default is true, so unread=0 is non-default
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['unread' => '0'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['unread' => '0'])
            ->willReturn('/?unread=0');

        $result = $extension->filterUrl();

        $this->assertEquals('/?unread=0', $result);
    }

    #[Test]
    public function pathWithFiltersFallsBackToDefaultWhenNoUserLoggedIn(): void
    {
        // Create extension with no user logged in
        $userService = $this->createMock(UserService::class);
        $userService->method('getCurrentUserOrNull')->willReturn(null);

        $extension = new FilterUrlExtension(
            $this->requestStack,
            $this->urlGenerator,
            $userService,
            $this->userPreferenceService,
        );

        // With no user, default is true, so unread=0 is non-default
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['unread' => '0'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item', ['fguid' => 'abc123', 'unread' => '0'])
            ->willReturn('/f/abc123?unread=0');

        $result = $extension->pathWithFilters('feed_item', [
            'fguid' => 'abc123',
        ]);

        $this->assertEquals('/f/abc123?unread=0', $result);
    }

    #[Test]
    public function filterUrlRemovesDefaultUnreadWhenPreferenceDisabled(): void
    {
        // Create extension with unreadOnly preference disabled
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $userService = $this->createMock(UserService::class);
        $userService->method('getCurrentUserOrNull')->willReturn($user);

        $userPreferenceService = $this->createMock(
            UserPreferenceService::class,
        );
        // unreadOnly is disabled (false) - so unread=0 is the default
        $userPreferenceService
            ->method('isUnreadOnlyEnabled')
            ->willReturn(false);

        $extension = new FilterUrlExtension(
            $this->requestStack,
            $this->urlGenerator,
            $userService,
            $userPreferenceService,
        );

        // With default unreadOnly=false, unread=0 should be removed (it's the default)
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', [])
            ->willReturn('/');

        $result = $extension->filterUrl(['unread' => 0]);

        $this->assertEquals('/', $result);
    }
}
