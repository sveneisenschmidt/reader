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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FilterParameterSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_UNREAD = false;
    public const DEFAULT_LIMIT = 50;

    #[Returns('array<string, string>')]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$response instanceof RedirectResponse) {
            return;
        }

        $filters = $this->getFilterParams($request);
        if (empty($filters)) {
            return;
        }

        $targetUrl = $response->getTargetUrl();
        $parsedUrl = parse_url($targetUrl);

        $existingQuery = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $existingQuery);
        }

        $mergedQuery = array_merge($filters, $existingQuery);
        $newQuery = http_build_query($mergedQuery);

        $newUrl =
            ($parsedUrl['path'] ?? '/').($newQuery ? '?'.$newQuery : '');
        $response->setTargetUrl($newUrl);
    }

    #[Returns('array<string, string|int>')]
    private function getFilterParams(Request $request): array
    {
        $filters = [];

        $unread = $request->query->getBoolean('unread', self::DEFAULT_UNREAD);

        if ($unread !== self::DEFAULT_UNREAD) {
            $filters['unread'] = '1';
        }

        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);
        if ($limit !== self::DEFAULT_LIMIT) {
            $filters['limit'] = $limit;
        }

        return $filters;
    }
}
