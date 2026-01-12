<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

use App\Enum\PreferenceDefault;
use App\EventSubscriber\FilterParameterSubscriber;
use App\Service\UserPreferenceService;
use App\Service\UserService;
use PhpStaticAnalysis\Attributes\Param;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FilterUrlExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private UserService $userService,
        private UserPreferenceService $userPreferenceService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('filter_url', [$this, 'filterUrl']),
            new TwigFunction('path_with_filters', [$this, 'pathWithFilters']),
        ];
    }

    #[Param(params: 'array<string, mixed>')]
    public function filterUrl(array $params = []): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $route = $request->attributes->get('_route');
        $routeParams = $request->attributes->get('_route_params', []);

        $user = $this->userService->getCurrentUserOrNull();
        $defaultUnread = $user
            ? $this->userPreferenceService->isUnreadOnlyEnabled($user->getId())
            : PreferenceDefault::UnreadOnly->asBool();

        $current = [
            'unread' => $request->query->getBoolean('unread', $defaultUnread)
                ? '1'
                : '0',
            'limit' => $request->query->getInt(
                'limit',
                FilterParameterSubscriber::DEFAULT_LIMIT,
            ),
        ];

        $merged = array_merge($current, $params);

        // Remove default values to keep URLs clean
        if (
            ($merged['unread'] === '1' || $merged['unread'] === 1)
            && $defaultUnread
        ) {
            unset($merged['unread']);
        } elseif (
            ($merged['unread'] === '0' || $merged['unread'] === 0)
            && !$defaultUnread
        ) {
            unset($merged['unread']);
        }
        if ($merged['limit'] === FilterParameterSubscriber::DEFAULT_LIMIT) {
            unset($merged['limit']);
        }

        return $this->urlGenerator->generate(
            $route,
            array_merge($routeParams, $merged),
        );
    }

    #[Param(params: 'array<string, mixed>')]
    public function pathWithFilters(string $route, array $params = []): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $filters = [];

        $user = $this->userService->getCurrentUserOrNull();
        $defaultUnread = $user
            ? $this->userPreferenceService->isUnreadOnlyEnabled($user->getId())
            : PreferenceDefault::UnreadOnly->asBool();
        $unread = $request->query->getBoolean('unread', $defaultUnread);

        if ($unread !== $defaultUnread) {
            $filters['unread'] = $unread ? '1' : '0';
        }

        $limit = $request->query->getInt(
            'limit',
            FilterParameterSubscriber::DEFAULT_LIMIT,
        );
        if ($limit !== FilterParameterSubscriber::DEFAULT_LIMIT) {
            $filters['limit'] = $limit;
        }

        return $this->urlGenerator->generate(
            $route,
            array_merge($params, $filters),
        );
    }
}
