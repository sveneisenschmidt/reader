<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\EventSubscriber;

use PhpStaticAnalysis\Attributes\Returns;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RememberMeRefreshSubscriber implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'REMEMBERME';
    private const LIFETIME = 7776000; // 90 days

    #[Returns('array<string, array{0: string, 1: int}>')]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $existingCookie = $request->cookies->get(self::COOKIE_NAME);
        if ($existingCookie === null) {
            return;
        }

        $responseCookies = $response->headers->getCookies();
        foreach ($responseCookies as $cookie) {
            if ($cookie->getName() === self::COOKIE_NAME) {
                return;
            }
        }

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_NAME)
                ->withValue($existingCookie)
                ->withExpires(time() + self::LIFETIME)
                ->withPath('/')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('lax'),
        );
    }
}
