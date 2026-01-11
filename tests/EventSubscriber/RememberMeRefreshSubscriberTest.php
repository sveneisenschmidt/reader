<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\RememberMeRefreshSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RememberMeRefreshSubscriberTest extends TestCase
{
    private RememberMeRefreshSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new RememberMeRefreshSubscriber();
    }

    private function createResponseEvent(
        Request $request,
        Response $response,
        int $requestType = HttpKernelInterface::MAIN_REQUEST,
    ): ResponseEvent {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, $requestType, $response);
    }

    #[Test]
    public function getSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = RememberMeRefreshSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertEquals(['onKernelResponse', -1000], $events[KernelEvents::RESPONSE]);
    }

    #[Test]
    public function onKernelResponseIgnoresSubRequests(): void
    {
        $request = Request::create('/');
        $request->cookies->set('REMEMBERME', 'token-value');
        $response = new Response();

        $event = $this->createResponseEvent($request, $response, HttpKernelInterface::SUB_REQUEST);
        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($response->headers->getCookies());
    }

    #[Test]
    public function onKernelResponseIgnoresRequestsWithoutRememberMeCookie(): void
    {
        $request = Request::create('/');
        $response = new Response();

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($response->headers->getCookies());
    }

    #[Test]
    public function onKernelResponseRefreshesCookie(): void
    {
        $request = Request::create('/');
        $request->cookies->set('REMEMBERME', 'token-value');
        $response = new Response();

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);

        $cookie = $cookies[0];
        $this->assertEquals('REMEMBERME', $cookie->getName());
        $this->assertEquals('token-value', $cookie->getValue());
        $this->assertGreaterThan(time() + 7776000 - 10, $cookie->getExpiresTime());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals('lax', $cookie->getSameSite());
    }

    #[Test]
    public function onKernelResponseDoesNotOverwriteExistingCookie(): void
    {
        $request = Request::create('/');
        $request->cookies->set('REMEMBERME', 'old-token');
        $response = new Response();
        $response->headers->setCookie(Cookie::create('REMEMBERME')->withValue('new-token'));

        $event = $this->createResponseEvent($request, $response);
        $this->subscriber->onKernelResponse($event);

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertEquals('new-token', $cookies[0]->getValue());
    }
}
