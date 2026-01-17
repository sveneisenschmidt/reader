<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Service\BookmarkService;
use App\Service\FeedItemService;
use App\Service\FeedViewService;
use App\Service\ReadStatusService;
use App\Service\SeenStatusService;
use App\Service\UrlValidatorService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Handles individual feed item operations: read/unread status, bookmarks, and external link opening.
 */
class FeedItemController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
        private BookmarkService $bookmarkService,
        private FeedItemService $feedItemService,
        private FeedViewService $feedViewService,
        private UrlValidatorService $urlValidatorService,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/mark-all-read', name: 'feed_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(Request $request): Response
    {
        $this->validateCsrfToken($request, 'mark_all_read');

        $user = $this->userService->getCurrentUser();
        $guids = $this->feedViewService->getAllItemGuids($user->getId());

        $this->readStatusService->markManyAsRead($user->getId(), $guids);
        $this->seenStatusService->markManyAsSeen($user->getId(), $guids);

        return $this->redirectToRoute('feed_index');
    }

    #[
        Route(
            '/s/{sguid}/mark-all-read',
            name: 'subscription_mark_all_read',
            requirements: ['sguid' => '[a-f0-9]{16}'],
            methods: ['POST'],
        ),
    ]
    public function markAllAsReadForSubscription(
        Request $request,
        string $sguid,
    ): Response {
        $this->validateCsrfToken($request, 'mark_all_read');

        $user = $this->userService->getCurrentUser();
        $guids = $this->feedViewService->getItemGuidsForSubscription(
            $user->getId(),
            $sguid,
        );

        $this->readStatusService->markManyAsRead($user->getId(), $guids);
        $this->seenStatusService->markManyAsSeen($user->getId(), $guids);

        return $this->redirectToRoute('subscription_show', ['sguid' => $sguid]);
    }

    #[
        Route(
            '/f/{fguid}/read',
            name: 'feed_item_mark_read',
            requirements: ['fguid' => '[a-f0-9]{16}'],
            methods: ['POST'],
        ),
    ]
    public function markAsRead(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, 'mark_read');

        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

        if ($request->request->get('redirect') === 'list') {
            return $this->redirectToRoute('feed_index');
        }

        return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
    }

    #[
        Route(
            '/f/{fguid}/unread',
            name: 'feed_item_mark_unread',
            requirements: ['fguid' => '[a-f0-9]{16}'],
            methods: ['POST'],
        ),
    ]
    public function markAsUnread(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, 'mark_read');

        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsUnread($user->getId(), $fguid);

        if ($request->request->get('redirect') === 'list') {
            return $this->redirectToRoute('feed_index');
        }

        return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
    }

    #[
        Route(
            '/s/{sguid}/f/{fguid}/read',
            name: 'feed_item_filtered_mark_read',
            requirements: [
                'sguid' => '[a-f0-9]{16}',
                'fguid' => '[a-f0-9]{16}',
            ],
            methods: ['POST'],
        ),
    ]
    public function markAsReadFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, 'mark_read');

        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

        if ($request->request->get('redirect') === 'list') {
            return $this->redirectToRoute('subscription_show', [
                'sguid' => $sguid,
            ]);
        }

        return $this->redirectToRoute('feed_item_filtered', [
            'sguid' => $sguid,
            'fguid' => $fguid,
        ]);
    }

    #[
        Route(
            '/s/{sguid}/f/{fguid}/unread',
            name: 'feed_item_filtered_mark_unread',
            requirements: [
                'sguid' => '[a-f0-9]{16}',
                'fguid' => '[a-f0-9]{16}',
            ],
            methods: ['POST'],
        ),
    ]
    public function markAsUnreadFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, 'mark_read');

        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsUnread($user->getId(), $fguid);

        if ($request->request->get('redirect') === 'list') {
            return $this->redirectToRoute('subscription_show', [
                'sguid' => $sguid,
            ]);
        }

        return $this->redirectToRoute('feed_item_filtered', [
            'sguid' => $sguid,
            'fguid' => $fguid,
        ]);
    }

    #[
        Route(
            '/f/{fguid}/bookmark',
            name: 'feed_item_bookmark',
            requirements: ['fguid' => '[a-f0-9]{16}'],
            methods: ['POST'],
        ),
    ]
    public function bookmark(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, 'bookmark');

        $user = $this->userService->getCurrentUser();
        $this->bookmarkService->bookmark($user->getId(), $fguid);

        return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
    }

    #[
        Route(
            '/f/{fguid}/unbookmark',
            name: 'feed_item_unbookmark',
            requirements: ['fguid' => '[a-f0-9]{16}'],
            methods: ['POST'],
        ),
    ]
    public function unbookmark(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, 'bookmark');

        $user = $this->userService->getCurrentUser();
        $this->bookmarkService->unbookmark($user->getId(), $fguid);

        return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
    }

    #[
        Route(
            '/s/{sguid}/f/{fguid}/bookmark',
            name: 'feed_item_filtered_bookmark',
            requirements: [
                'sguid' => '[a-f0-9]{16}',
                'fguid' => '[a-f0-9]{16}',
            ],
            methods: ['POST'],
        ),
    ]
    public function bookmarkFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, 'bookmark');

        $user = $this->userService->getCurrentUser();
        $this->bookmarkService->bookmark($user->getId(), $fguid);

        return $this->redirectToRoute('feed_item_filtered', [
            'sguid' => $sguid,
            'fguid' => $fguid,
        ]);
    }

    #[
        Route(
            '/s/{sguid}/f/{fguid}/unbookmark',
            name: 'feed_item_filtered_unbookmark',
            requirements: [
                'sguid' => '[a-f0-9]{16}',
                'fguid' => '[a-f0-9]{16}',
            ],
            methods: ['POST'],
        ),
    ]
    public function unbookmarkFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, 'bookmark');

        $user = $this->userService->getCurrentUser();
        $this->bookmarkService->unbookmark($user->getId(), $fguid);

        return $this->redirectToRoute('feed_item_filtered', [
            'sguid' => $sguid,
            'fguid' => $fguid,
        ]);
    }

    #[
        Route(
            '/f/{fguid}/open',
            name: 'feed_item_open',
            requirements: ['fguid' => '[a-f0-9]{16}'],
            methods: ['GET'],
        ),
    ]
    public function open(Request $request, string $fguid): Response
    {
        $url = $request->query->get('url');
        if (!$url) {
            return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
        }

        $feedItem = $this->feedItemService->findByGuid($fguid);

        if (!$feedItem || !$this->urlValidatorService->isUrlAllowedForFeedItem($url, $feedItem)) {
            return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
        }

        $user = $this->userService->getCurrentUser();
        $userId = $user->getId();

        $this->readStatusService->markAsRead($userId, $fguid);
        $this->seenStatusService->markAsSeen($userId, $fguid);

        return $this->redirect($url);
    }

    private function validateCsrfToken(Request $request, string $tokenId): void
    {
        $token = new CsrfToken($tokenId, $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
