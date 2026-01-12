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
use App\Repository\Content\FeedItemRepository;
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

class FeedController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private SubscriptionService $subscriptionService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
        private FeedViewService $feedViewService,
        private UserPreferenceService $userPreferenceService,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private MessageBusInterface $messageBus,
        private FeedItemRepository $feedItemRepository,
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
        $userId = $user->getId();
        $this->readStatusService->markAsRead($userId, $fguid);

        if ($request->request->get('stay') === '1') {
            return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
        }

        $limit = $request->query->getInt(
            'limit',
            FilterParameterSubscriber::DEFAULT_LIMIT,
        );

        $nextFguid = $this->userPreferenceService->isShowNextUnreadEnabled(
            $userId,
        )
            ? $this->feedViewService->findNextUnreadItemGuid(
                $userId,
                null,
                $fguid,
                $limit,
            )
            : $this->feedViewService->findNextItemGuid(
                $userId,
                null,
                $fguid,
                $limit,
            );

        return $nextFguid
            ? $this->redirectToRoute('feed_item', ['fguid' => $nextFguid])
            : $this->redirectToRoute('feed_index');
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

        return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
    }

    #[
        Route(
            '/f/{fguid}/read-stay',
            name: 'feed_item_mark_read_stay',
            requirements: ['fguid' => '[a-f0-9]{16}'],
            methods: ['POST'],
        ),
    ]
    public function markAsReadStay(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, 'mark_read');
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

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
        $userId = $user->getId();
        $this->readStatusService->markAsRead($userId, $fguid);

        if ($request->request->get('stay') === '1') {
            return $this->redirectToRoute('feed_item_filtered', [
                'sguid' => $sguid,
                'fguid' => $fguid,
            ]);
        }

        $limit = $request->query->getInt(
            'limit',
            FilterParameterSubscriber::DEFAULT_LIMIT,
        );

        $nextFguid = $this->userPreferenceService->isShowNextUnreadEnabled(
            $userId,
        )
            ? $this->feedViewService->findNextUnreadItemGuid(
                $userId,
                $sguid,
                $fguid,
                $limit,
            )
            : $this->feedViewService->findNextItemGuid(
                $userId,
                $sguid,
                $fguid,
                $limit,
            );

        return $nextFguid
            ? $this->redirectToRoute('feed_item_filtered', [
                'sguid' => $sguid,
                'fguid' => $nextFguid,
            ])
            : $this->redirectToRoute('subscription_show', ['sguid' => $sguid]);
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

        return $this->redirectToRoute('feed_item_filtered', [
            'sguid' => $sguid,
            'fguid' => $fguid,
        ]);
    }

    #[
        Route(
            '/s/{sguid}/f/{fguid}/read-stay',
            name: 'feed_item_filtered_mark_read_stay',
            requirements: [
                'sguid' => '[a-f0-9]{16}',
                'fguid' => '[a-f0-9]{16}',
            ],
            methods: ['POST'],
        ),
    ]
    public function markAsReadFilteredStay(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, 'mark_read');
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

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

        $feedItem = $this->feedItemRepository->findByGuid($fguid);
        if (!$feedItem || !$this->isUrlAllowed($url, $feedItem)) {
            return $this->redirectToRoute('feed_item', ['fguid' => $fguid]);
        }

        $user = $this->userService->getCurrentUser();
        $userId = $user->getId();
        $this->readStatusService->markAsRead($userId, $fguid);
        $this->seenStatusService->markAsSeen($userId, $fguid);

        return $this->redirect($url);
    }

    private function isUrlAllowed(
        string $url,
        \App\Entity\Content\FeedItem $feedItem,
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
    ): Response {
        $user = $this->userService->getCurrentUser();
        $defaultUnread = $this->userPreferenceService->isUnreadOnlyEnabled(
            $user->getId(),
        );
        $unread = $request->query->getBoolean('unread', $defaultUnread);
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

        return $this->render('feed/index.html.twig', [
            'feeds' => $viewData['feeds'],
            'items' => $viewData['items'],
            'allItemsCount' => $viewData['allItemsCount'],
            'activeItem' => $viewData['activeItem'],
            'activeFeed' => $sguid,
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
