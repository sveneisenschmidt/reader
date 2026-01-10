<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\FilterParameterSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class FilterParameterSubscriberTest extends TestCase
{
    private FilterParameterSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new FilterParameterSubscriber();
    }

    private function createResponseEvent(
        Request $request,
        Response $response,
    ): ResponseEvent {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }

    #[Test]
    public function getSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = FilterParameterSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertEquals('onKernelResponse', $events[KernelEvents::RESPONSE]);
    }

    #[Test]
    public function onKernelResponseIgnoresNonRedirectResponses(): void
    {
        $request = Request::create('/?unread=1');
        $response = new Response('content');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        // Response should be unchanged
        $this->assertEquals('content', $response->getContent());
    }

    #[Test]
    public function onKernelResponseIgnoresRedirectWithoutFilters(): void
    {
        $request = Request::create('/');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertEquals('/target', $response->getTargetUrl());
    }

    #[Test]
    public function onKernelResponseAddsUnreadFilter(): void
    {
        $request = Request::create('/?unread=1');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertEquals('/target?unread=1', $response->getTargetUrl());
    }

    #[Test]
    public function onKernelResponseAddsLimitFilter(): void
    {
        $request = Request::create('/?limit=25');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertEquals('/target?limit=25', $response->getTargetUrl());
    }

    #[Test]
    public function onKernelResponseIgnoresDefaultLimit(): void
    {
        $request = Request::create('/?limit=50');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        // Default limit (50) should not be added
        $this->assertEquals('/target', $response->getTargetUrl());
    }

    #[Test]
    public function onKernelResponseCombinesFilters(): void
    {
        $request = Request::create('/?unread=1&limit=25');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertStringContainsString('unread=1', $response->getTargetUrl());
        $this->assertStringContainsString('limit=25', $response->getTargetUrl());
    }

    #[Test]
    public function onKernelResponsePreservesExistingQueryParams(): void
    {
        $request = Request::create('/?unread=1');
        $response = new RedirectResponse('/target?existing=value');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertStringContainsString('unread=1', $response->getTargetUrl());
        $this->assertStringContainsString('existing=value', $response->getTargetUrl());
    }
}
