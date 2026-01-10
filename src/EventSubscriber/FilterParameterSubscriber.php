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

        if ($request->query->getBoolean('unread', false)) {
            $filters['unread'] = '1';
        }

        $limit = $request->query->getInt('limit', 50);
        if ($limit !== 50) {
            $filters['limit'] = $limit;
        }

        return $filters;
    }
}
