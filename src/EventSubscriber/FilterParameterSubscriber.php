<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\EventSubscriber;

use App\Service\UserPreferenceService;
use App\Service\UserService;
use PhpStaticAnalysis\Attributes\Returns;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class FilterParameterSubscriber implements EventSubscriberInterface
{
    public const DEFAULT_LIMIT = 50;

    public function __construct(
        private UserService $userService,
        private UserPreferenceService $userPreferenceService,
    ) {
    }

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

        $user = $this->userService->getCurrentUserOrNull();
        if (null === $user) {
            return $filters;
        }

        $defaultUnread = $this->userPreferenceService->isUnreadOnlyEnabled(
            $user->getId(),
        );
        $unread = $request->query->getBoolean('unread', $defaultUnread);

        if ($unread !== $defaultUnread) {
            $filters['unread'] = $unread ? '1' : '0';
        }

        $limit = $request->query->getInt('limit', self::DEFAULT_LIMIT);
        if ($limit !== self::DEFAULT_LIMIT) {
            $filters['limit'] = $limit;
        }

        return $filters;
    }
}
