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
use App\Repository\BookmarkStatusRepository;
use App\Repository\FeedItemRepository;
use App\Service\FeedViewService;
use App\Service\ReadStatusService;
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
use Symfony\Component\Stopwatch\Stopwatch;

class FeedController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private SubscriptionService $subscriptionService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
        private BookmarkStatusRepository $bookmarkStatusRepository,
        private FeedViewService $feedViewService,
        private UserPreferenceService $userPreferenceService,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private MessageBusInterface $messageBus,
        private FeedItemRepository $feedItemRepository,
        private ?Stopwatch $stopwatch = null,
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

        $bookmarksCount = $this->bookmarkStatusRepository->countByUser(
            $user->getId(),
        );

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

    #[Route('/mark-all-read', name: 'feed_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(Request $request): Response
    {
        $this->validateCsrfToken($request, 'mark_all_read');

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $this->stopwatch?->start('getAllItemGuids', 'controller');
        $guids = $this->feedViewService->getAllItemGuids($user->getId());
        $this->stopwatch?->stop('getAllItemGuids');

        $this->stopwatch?->start('markManyAsRead', 'controller');
        $this->readStatusService->markManyAsRead($user->getId(), $guids);
        $this->stopwatch?->stop('markManyAsRead');

        $this->stopwatch?->start('markManyAsSeen', 'controller');
        $this->seenStatusService->markManyAsSeen($user->getId(), $guids);
        $this->stopwatch?->stop('markManyAsSeen');

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

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $this->stopwatch?->start('getItemGuidsForSubscription', 'controller');
        $guids = $this->feedViewService->getItemGuidsForSubscription(
            $user->getId(),
            $sguid,
        );
        $this->stopwatch?->stop('getItemGuidsForSubscription');

        $this->stopwatch?->start('markManyAsRead', 'controller');
        $this->readStatusService->markManyAsRead($user->getId(), $guids);
        $this->stopwatch?->stop('markManyAsRead');

        $this->stopwatch?->start('markManyAsSeen', 'controller');
        $this->seenStatusService->markManyAsSeen($user->getId(), $guids);
        $this->stopwatch?->stop('markManyAsSeen');

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

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $this->stopwatch?->start('markAsRead', 'controller');
        $this->readStatusService->markAsRead($user->getId(), $fguid);
        $this->stopwatch?->stop('markAsRead');

        // redirect=list: back to feed list, redirect=article: stay on article
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

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $this->stopwatch?->start('markAsUnread', 'controller');
        $this->readStatusService->markAsUnread($user->getId(), $fguid);
        $this->stopwatch?->stop('markAsUnread');

        // redirect=list: back to feed list, redirect=article: stay on article
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

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $this->stopwatch?->start('markAsRead', 'controller');
        $this->readStatusService->markAsRead($user->getId(), $fguid);
        $this->stopwatch?->stop('markAsRead');

        // redirect=list: back to subscription list, redirect=article: stay on article
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

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $this->stopwatch?->start('markAsUnread', 'controller');
        $this->readStatusService->markAsUnread($user->getId(), $fguid);
        $this->stopwatch?->stop('markAsUnread');

        // redirect=list: back to subscription list, redirect=article: stay on article
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
        $this->bookmarkStatusRepository->bookmark($user->getId(), $fguid);

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
        $this->bookmarkStatusRepository->unbookmark($user->getId(), $fguid);

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
        $this->bookmarkStatusRepository->bookmark($user->getId(), $fguid);

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
        $this->bookmarkStatusRepository->unbookmark($user->getId(), $fguid);

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

        $this->stopwatch?->start('findByGuid', 'controller');
        $feedItem = $this->feedItemRepository->findByGuid($fguid);
        $this->stopwatch?->stop('findByGuid');

        if (!$feedItem || !$this->isUrlAllowed($url, $feedItem)) {
            return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
        }

        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $userId = $user->getId();

        $this->stopwatch?->start('markAsRead', 'controller');
        $this->readStatusService->markAsRead($userId, $fguid);
        $this->stopwatch?->stop('markAsRead');

        $this->stopwatch?->start('markAsSeen', 'controller');
        $this->seenStatusService->markAsSeen($userId, $fguid);
        $this->stopwatch?->stop('markAsSeen');

        return $this->redirect($url);
    }

    private function isUrlAllowed(
        string $url,
        \App\Entity\FeedItem $feedItem,
    ): bool {
        if ($feedItem->getLink() === $url) {
            return true;
        }

        $content = $feedItem->getExcerpt();
        if (empty($content)) {
            return false;
        }

        $crawler = new \Symfony\Component\DomCrawler\Crawler($content);
        $hrefs = $crawler->filter('a')->each(fn ($node) => $node->attr('href'));

        return in_array($url, $hrefs, true);
    }

    private function renderFeedView(
        Request $request,
        ?string $sguid = null,
        ?string $fguid = null,
        bool $bookmarksOnly = false,
    ): Response {
        $this->stopwatch?->start('getCurrentUser', 'controller');
        $user = $this->userService->getCurrentUser();
        $this->stopwatch?->stop('getCurrentUser');

        $unread = $request->query->getBoolean(
            'unread',
            FilterParameterSubscriber::DEFAULT_UNREAD,
        );
        $limit = $request->query->getInt(
            'limit',
            FilterParameterSubscriber::DEFAULT_LIMIT,
        );

        $this->stopwatch?->start('getViewData', 'controller');
        $viewData = $this->feedViewService->getViewData(
            $user->getId(),
            $sguid,
            $fguid,
            $unread,
            $limit,
            $bookmarksOnly,
        );
        $this->stopwatch?->stop('getViewData');

        if ($viewData['activeItem']) {
            $this->stopwatch?->start('markAsSeen', 'controller');
            $this->seenStatusService->markAsSeen(
                $user->getId(),
                $viewData['activeItem']['guid'],
            );
            $this->stopwatch?->stop('markAsSeen');
        }

        $this->stopwatch?->start('isPullToRefreshEnabled', 'controller');
        $pullToRefresh = $this->userPreferenceService->isPullToRefreshEnabled(
            $user->getId(),
        );
        $this->stopwatch?->stop('isPullToRefreshEnabled');

        $this->stopwatch?->start('isBookmarksEnabled', 'controller');
        $bookmarksEnabled = $this->userPreferenceService->isBookmarksEnabled(
            $user->getId(),
        );
        $this->stopwatch?->stop('isBookmarksEnabled');

        $this->stopwatch?->start('render', 'controller');
        $response = $this->render('feed/index.html.twig', [
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
        $this->stopwatch?->stop('render');

        return $response;
    }

    private function validateCsrfToken(Request $request, string $tokenId): void
    {
        $token = new CsrfToken($tokenId, $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
