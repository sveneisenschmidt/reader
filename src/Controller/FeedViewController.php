<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Enum\MessageSource;
use App\EventSubscriber\FilterParameterSubscriber;
use App\Message\RefreshFeedsMessage;
use App\Service\BookmarkService;
use App\Service\FeedViewService;
use App\Service\SeenStatusService;
use App\Service\SubscriptionService;
use App\Service\UserPreferenceService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Handles feed view operations: displaying feeds, filtering, and refresh.
 */
class FeedViewController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private SubscriptionService $subscriptionService,
        private SeenStatusService $seenStatusService,
        private BookmarkService $bookmarkService,
        private FeedViewService $feedViewService,
        private UserPreferenceService $userPreferenceService,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/', name: 'feed_index')]
    public function index(Request $request): Response
    {
        $user = $this->userService->getCurrentUser();
        if ($this->subscriptionService->countByUser($user->getId()) === 0) {
            return $this->redirectToRoute('onboarding');
        }

        return $this->renderFeedView($request);
    }

    #[Route('/bookmarks', name: 'feed_bookmarks')]
    public function bookmarks(Request $request): Response
    {
        $user = $this->userService->getCurrentUser();

        if (!$this->userPreferenceService->isBookmarksEnabled($user->getId())) {
            return $this->redirectToRoute('feed_index');
        }

        $bookmarksCount = $this->bookmarkService->countByUser($user->getId());

        if ($bookmarksCount === 0) {
            return $this->redirectToRoute('feed_index');
        }

        return $this->renderFeedView($request, null, null, true);
    }

    #[Route('/refresh', name: 'feed_refresh', methods: ['POST'])]
    public function refresh(Request $request): Response
    {
        $this->validateCsrfToken($request, 'refresh');
        $this->messageBus->dispatch(
            new RefreshFeedsMessage(MessageSource::Manual),
        );

        $referer = $request->headers->get('referer');
        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $currentHost = $request->getHost();
            if ($refererHost === $currentHost) {
                return $this->redirect($referer);
            }
        }

        return $this->redirectToRoute('feed_index');
    }

    #[
        Route(
            '/f/{fguid}',
            name: 'feed_item',
            requirements: ['fguid' => '[a-f0-9]{16}'],
        ),
    ]
    public function item(Request $request, string $fguid): Response
    {
        return $this->renderFeedView($request, null, $fguid);
    }

    #[
        Route(
            '/s/{sguid}',
            name: 'subscription_show',
            requirements: ['sguid' => '[a-f0-9]{16}'],
        ),
    ]
    public function subscription(Request $request, string $sguid): Response
    {
        return $this->renderFeedView($request, $sguid);
    }

    #[
        Route(
            '/s/{sguid}/f/{fguid}',
            name: 'feed_item_filtered',
            requirements: [
                'sguid' => '[a-f0-9]{16}',
                'fguid' => '[a-f0-9]{16}',
            ],
        ),
    ]
    public function itemFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        return $this->renderFeedView($request, $sguid, $fguid);
    }

    private function renderFeedView(
        Request $request,
        ?string $sguid = null,
        ?string $fguid = null,
        bool $bookmarksOnly = false,
    ): Response {
        $user = $this->userService->getCurrentUser();

        $unread = $request->query->getBoolean(
            'unread',
            FilterParameterSubscriber::DEFAULT_UNREAD,
        );
        $limit = $request->query->getInt(
            'limit',
            FilterParameterSubscriber::DEFAULT_LIMIT,
        );

        $viewData = $this->feedViewService->getViewData(
            $user->getId(),
            $sguid,
            $fguid,
            $unread,
            $limit,
            $bookmarksOnly,
        );

        if ($viewData['activeItem']) {
            $this->seenStatusService->markAsSeen(
                $user->getId(),
                $viewData['activeItem']['guid'],
            );
        }

        $pullToRefresh = $this->userPreferenceService->isPullToRefreshEnabled(
            $user->getId(),
        );

        $bookmarksEnabled = $this->userPreferenceService->isBookmarksEnabled(
            $user->getId(),
        );

        return $this->render('feed/index.html.twig', [
            'feeds' => $viewData['feeds'],
            'groupedFeeds' => $viewData['groupedFeeds'],
            'ungroupedFeeds' => $viewData['ungroupedFeeds'],
            'items' => $viewData['items'],
            'allItemsCount' => $viewData['allItemsCount'],
            'bookmarksCount' => $viewData['bookmarksCount'],
            'activeItem' => $viewData['activeItem'],
            'activeFeed' => $sguid,
            'bookmarksOnly' => $bookmarksOnly,
            'bookmarksEnabled' => $bookmarksEnabled,
            'unread' => $unread,
            'limit' => $limit,
            'pullToRefresh' => $pullToRefresh,
        ]);
    }

    private function validateCsrfToken(Request $request, string $tokenId): void
    {
        $token = new CsrfToken($tokenId, $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
