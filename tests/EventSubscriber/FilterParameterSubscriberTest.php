<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\EventSubscriber;

use App\Entity\Users\User;
use App\EventSubscriber\FilterParameterSubscriber;
use App\Service\UserPreferenceService;
use App\Service\UserService;
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
    private UserService $userService;
    private UserPreferenceService $userPreferenceService;

    protected function setUp(): void
    {
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

        $this->subscriber = new FilterParameterSubscriber(
            $this->userService,
            $this->userPreferenceService,
        );
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
        $this->assertEquals(
            'onKernelResponse',
            $events[KernelEvents::RESPONSE],
        );
    }

    #[Test]
    public function onKernelResponseIgnoresNonRedirectResponses(): void
    {
        $request = Request::create('/?unread=0');
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
    public function onKernelResponseAddsUnreadFilterWhenNotDefault(): void
    {
        // With default unreadOnly=true, unread=0 should be preserved
        $request = Request::create('/?unread=0');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertEquals('/target?unread=0', $response->getTargetUrl());
    }

    #[Test]
    public function onKernelResponseIgnoresDefaultUnreadValue(): void
    {
        // With default unreadOnly=true, unread=1 is the default and should not be added
        $request = Request::create('/?unread=1');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertEquals('/target', $response->getTargetUrl());
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
        $request = Request::create('/?unread=0&limit=25');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertStringContainsString(
            'unread=0',
            $response->getTargetUrl(),
        );
        $this->assertStringContainsString(
            'limit=25',
            $response->getTargetUrl(),
        );
    }

    #[Test]
    public function onKernelResponsePreservesExistingQueryParams(): void
    {
        $request = Request::create('/?unread=0');
        $response = new RedirectResponse('/target?existing=value');

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertStringContainsString(
            'unread=0',
            $response->getTargetUrl(),
        );
        $this->assertStringContainsString(
            'existing=value',
            $response->getTargetUrl(),
        );
    }

    #[Test]
    public function onKernelResponseRespectsUserPreferenceWhenDisabled(): void
    {
        // Create subscriber with unreadOnly preference disabled
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

        $subscriber = new FilterParameterSubscriber(
            $userService,
            $userPreferenceService,
        );

        // With default unreadOnly=false, unread=1 should be preserved
        $request = Request::create('/?unread=1');
        $response = new RedirectResponse('/target');

        $event = $this->createResponseEvent($request, $response);
        $subscriber->onKernelResponse($event);

        $this->assertEquals('/target?unread=1', $response->getTargetUrl());
    }
}
