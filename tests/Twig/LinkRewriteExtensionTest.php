<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Twig;

use App\Twig\LinkRewriteExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LinkRewriteExtensionTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private LinkRewriteExtension $extension;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->extension = new LinkRewriteExtension($this->urlGenerator);
    }

    #[Test]
    public function getFiltersReturnsExpectedFilters(): void
    {
        $filters = $this->extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertEquals('rewrite_links', $filters[0]->getName());
    }

    #[Test]
    public function rewriteLinksReturnsEmptyStringForEmptyInput(): void
    {
        $result = $this->extension->rewriteLinks('', 'abc123');

        $this->assertEquals('', $result);
    }

    #[Test]
    public function rewriteLinksRewritesSimpleLink(): void
    {
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item_open', [
                'fguid' => 'abc123',
                'url' => 'https://example.com/page',
            ])
            ->willReturn('/f/abc123/open?url=https%3A%2F%2Fexample.com%2Fpage');

        $html = '<a href="https://example.com/page">Link</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringContainsString(
            'href="/f/abc123/open?url=https%3A%2F%2Fexample.com%2Fpage"',
            $result,
        );
        $this->assertStringContainsString('target="_blank"', $result);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    #[Test]
    public function rewriteLinksPreservesAnchorLinks(): void
    {
        $html = '<a href="#section">Jump to section</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertEquals($html, $result);
    }

    #[Test]
    public function rewriteLinksPreservesJavascriptLinks(): void
    {
        $html = '<a href="javascript:void(0)">Click me</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertEquals($html, $result);
    }

    #[Test]
    public function rewriteLinksHandlesMultipleLinks(): void
    {
        $this->urlGenerator
            ->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(function ($route, $params) {
                return '/f/abc123/open?url='.urlencode($params['url']);
            });

        $html =
            '<p><a href="https://example.com/1">First</a> and <a href="https://example.com/2">Second</a></p>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringContainsString(
            '/f/abc123/open?url=https%3A%2F%2Fexample.com%2F1',
            $result,
        );
        $this->assertStringContainsString(
            '/f/abc123/open?url=https%3A%2F%2Fexample.com%2F2',
            $result,
        );
    }

    #[Test]
    public function rewriteLinksPreservesExistingAttributes(): void
    {
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('/f/abc123/open?url=https%3A%2F%2Fexample.com');

        $html =
            '<a class="my-class" href="https://example.com" data-id="123">Link</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringContainsString('class="my-class"', $result);
        $this->assertStringContainsString('data-id="123"', $result);
    }

    #[Test]
    public function rewriteLinksHandlesSingleQuotedHref(): void
    {
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('feed_item_open', [
                'fguid' => 'abc123',
                'url' => 'https://example.com/page',
            ])
            ->willReturn('/f/abc123/open?url=https%3A%2F%2Fexample.com%2Fpage');

        $html = "<a href='https://example.com/page'>Link</a>";
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringContainsString(
            'href="/f/abc123/open?url=https%3A%2F%2Fexample.com%2Fpage"',
            $result,
        );
    }

    #[Test]
    public function rewriteLinksHandlesTextWithoutLinks(): void
    {
        $html = '<p>Just some text without links</p>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertEquals($html, $result);
    }

    #[Test]
    public function rewriteLinksEscapesUrlProperly(): void
    {
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn(
                '/f/abc123/open?url=https%3A%2F%2Fexample.com%2Fpage%3Ffoo%3Dbar%26baz%3D1',
            );

        $html = '<a href="https://example.com/page?foo=bar&baz=1">Link</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringContainsString(
            'href="/f/abc123/open?url=https%3A%2F%2Fexample.com%2Fpage%3Ffoo%3Dbar%26baz%3D1"',
            $result,
        );
    }

    #[Test]
    public function rewriteLinksRemovesEventHandlers(): void
    {
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('/f/abc123/open?url=https%3A%2F%2Fexample.com');

        $html =
            '<a onclick="alert(\'xss\')" href="https://example.com" onmouseover="evil()">Link</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('onmouseover', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringNotContainsString('evil', $result);
        $this->assertStringContainsString('href="/f/abc123/open', $result);
    }

    #[Test]
    public function rewriteLinksRemovesVariousEventHandlers(): void
    {
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('/f/abc123/open?url=https%3A%2F%2Fexample.com');

        $html =
            '<a onload="x()" onerror="y()" onfocus="z()" href="https://example.com">Link</a>';
        $result = $this->extension->rewriteLinks($html, 'abc123');

        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('onfocus', $result);
    }
}
