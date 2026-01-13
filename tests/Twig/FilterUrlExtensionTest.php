<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Twig;

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
    private FilterUrlExtension $extension;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        $this->extension = new FilterUrlExtension(
            $this->requestStack,
            $this->urlGenerator,
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
        // With default unread=false, unread=1 is non-default and should be preserved
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

        $result = $this->extension->filterUrl();

        $this->assertEquals('/?unread=1', $result);
    }

    #[Test]
    public function filterUrlMergesNewParams(): void
    {
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', ['unread' => '1'])
            ->willReturn('/?unread=1');

        $result = $this->extension->filterUrl(['unread' => '1']);

        $this->assertEquals('/?unread=1', $result);
    }

    #[Test]
    public function filterUrlRemovesDefaultValues(): void
    {
        // With default unread=false, unread=0 is the default and should be removed
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', [])
            ->willReturn('/');

        $result = $this->extension->filterUrl(['unread' => '0', 'limit' => 50]);

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
            ->with('subscription_show', ['sguid' => 'abc123', 'unread' => '1'])
            ->willReturn('/s/abc123?unread=1');

        $result = $this->extension->filterUrl(['unread' => '1']);

        $this->assertEquals('/s/abc123?unread=1', $result);
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
        // With default unread=false, unread=1 is non-default and should be preserved
        $request = $this->createMockRequest(
            'feed_index',
            [],
            ['unread' => '1'],
        );
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item', ['fguid' => 'abc123', 'unread' => '1'])
            ->willReturn('/f/abc123?unread=1');

        $result = $this->extension->pathWithFilters('feed_item', [
            'fguid' => 'abc123',
        ]);

        $this->assertEquals('/f/abc123?unread=1', $result);
    }

    #[Test]
    public function pathWithFiltersDoesNotIncludeUnreadWhenDefault(): void
    {
        // With default unread=false, no unread param means default=false, so no param needed
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
        // With default unread=false, unread=0 is the default and should be removed
        $request = $this->createMockRequest('feed_index');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_index', [])
            ->willReturn('/');

        $result = $this->extension->filterUrl(['unread' => 0]);

        $this->assertEquals('/', $result);
    }

    #[Test]
    public function filterUrlPreservesUnreadWhenEnabled(): void
    {
        // With default unread=false, unread=1 is non-default and should be preserved
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

        $result = $this->extension->filterUrl();

        $this->assertEquals('/?unread=1', $result);
    }
}
