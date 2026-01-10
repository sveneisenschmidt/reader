<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

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

        $current = [
            'unread' => $request->query->getBoolean('unread', false)
                ? '1'
                : '0',
            'limit' => $request->query->getInt('limit', 50),
        ];

        $merged = array_merge($current, $params);

        // Remove default values to keep URLs clean
        if ($merged['unread'] === '0' || $merged['unread'] === 0) {
            unset($merged['unread']);
        }
        if ($merged['limit'] === 50) {
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

        if ($request->query->getBoolean('unread', false)) {
            $filters['unread'] = '1';
        }

        $limit = $request->query->getInt('limit', 50);
        if ($limit !== 50) {
            $filters['limit'] = $limit;
        }

        return $this->urlGenerator->generate(
            $route,
            array_merge($params, $filters),
        );
    }
}
