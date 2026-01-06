<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Service\FeedFetcher;
use App\Service\FeedViewService;
use App\Service\ReadStatusService;
use App\Service\SeenStatusService;
use App\Service\SubscriptionService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class FeedController extends AbstractController
{
    public function __construct(
        private FeedFetcher $feedFetcher,
        private UserService $userService,
        private SubscriptionService $subscriptionService,
        private ReadStatusService $readStatusService,
        private SeenStatusService $seenStatusService,
        private FeedViewService $feedViewService,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route("/", name: "feed_index")]
    public function index(Request $request): Response
    {
        return $this->renderFeedView($request);
    }

    #[Route("/refresh", name: "feed_refresh", methods: ["POST"])]
    public function refresh(): Response
    {
        $user = $this->userService->getCurrentUser();
        $feedUrls = $this->subscriptionService->getFeedUrls($user->getId());
        $this->feedFetcher->refreshAllFeeds($feedUrls);
        $this->subscriptionService->updateRefreshTimestamps($user->getId());

        return new Response("OK", Response::HTTP_OK);
    }

    #[
        Route(
            "/f/{fguid}",
            name: "feed_item",
            requirements: ["fguid" => "[a-f0-9]{16}"],
        ),
    ]
    public function item(Request $request, string $fguid): Response
    {
        return $this->renderFeedView($request, null, $fguid);
    }

    #[
        Route(
            "/s/{sguid}",
            name: "subscription_show",
            requirements: ["sguid" => "[a-f0-9]{16}"],
        ),
    ]
    public function subscription(Request $request, string $sguid): Response
    {
        return $this->renderFeedView($request, $sguid);
    }

    #[
        Route(
            "/s/{sguid}/f/{fguid}",
            name: "feed_item_filtered",
            requirements: [
                "sguid" => "[a-f0-9]{16}",
                "fguid" => "[a-f0-9]{16}",
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

    #[Route("/mark-all-read", name: "feed_mark_all_read", methods: ["POST"])]
    public function markAllAsRead(Request $request): Response
    {
        $this->validateCsrfToken($request, "mark_all_read");
        $user = $this->userService->getCurrentUser();
        $guids = $this->feedViewService->getAllItemGuids($user->getId());
        $this->readStatusService->markManyAsRead($user->getId(), $guids);
        $this->seenStatusService->markManyAsSeen($user->getId(), $guids);

        return $this->redirectToRoute("feed_index");
    }

    #[
        Route(
            "/s/{sguid}/mark-all-read",
            name: "subscription_mark_all_read",
            requirements: ["sguid" => "[a-f0-9]{16}"],
            methods: ["POST"],
        ),
    ]
    public function markAllAsReadForSubscription(
        Request $request,
        string $sguid,
    ): Response {
        $this->validateCsrfToken($request, "mark_all_read");
        $user = $this->userService->getCurrentUser();
        $guids = $this->feedViewService->getItemGuidsForSubscription(
            $user->getId(),
            $sguid,
        );
        $this->readStatusService->markManyAsRead($user->getId(), $guids);
        $this->seenStatusService->markManyAsSeen($user->getId(), $guids);

        return $this->redirectToRoute("subscription_show", ["sguid" => $sguid]);
    }

    #[
        Route(
            "/f/{fguid}/read",
            name: "feed_item_mark_read",
            requirements: ["fguid" => "[a-f0-9]{16}"],
            methods: ["POST"],
        ),
    ]
    public function markAsRead(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, "mark_read");
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

        $nextFguid = $this->feedViewService->findNextItemGuid(
            $user->getId(),
            null,
            $fguid,
        );

        return $nextFguid
            ? $this->redirectToRoute("feed_item", ["fguid" => $nextFguid])
            : $this->redirectToRoute("feed_index");
    }

    #[
        Route(
            "/f/{fguid}/unread",
            name: "feed_item_mark_unread",
            requirements: ["fguid" => "[a-f0-9]{16}"],
            methods: ["POST"],
        ),
    ]
    public function markAsUnread(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, "mark_read");
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsUnread($user->getId(), $fguid);

        return $this->redirectToRoute("feed_item", ["fguid" => $fguid]);
    }

    #[
        Route(
            "/f/{fguid}/read-stay",
            name: "feed_item_mark_read_stay",
            requirements: ["fguid" => "[a-f0-9]{16}"],
            methods: ["POST"],
        ),
    ]
    public function markAsReadStay(Request $request, string $fguid): Response
    {
        $this->validateCsrfToken($request, "mark_read");
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

        return $this->redirectToRoute("feed_item", ["fguid" => $fguid]);
    }

    #[
        Route(
            "/s/{sguid}/f/{fguid}/read",
            name: "feed_item_filtered_mark_read",
            requirements: [
                "sguid" => "[a-f0-9]{16}",
                "fguid" => "[a-f0-9]{16}",
            ],
            methods: ["POST"],
        ),
    ]
    public function markAsReadFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, "mark_read");
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

        $nextFguid = $this->feedViewService->findNextItemGuid(
            $user->getId(),
            $sguid,
            $fguid,
        );

        return $nextFguid
            ? $this->redirectToRoute("feed_item_filtered", [
                "sguid" => $sguid,
                "fguid" => $nextFguid,
            ])
            : $this->redirectToRoute("subscription_show", ["sguid" => $sguid]);
    }

    #[
        Route(
            "/s/{sguid}/f/{fguid}/unread",
            name: "feed_item_filtered_mark_unread",
            requirements: [
                "sguid" => "[a-f0-9]{16}",
                "fguid" => "[a-f0-9]{16}",
            ],
            methods: ["POST"],
        ),
    ]
    public function markAsUnreadFiltered(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, "mark_read");
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsUnread($user->getId(), $fguid);

        return $this->redirectToRoute("feed_item_filtered", [
            "sguid" => $sguid,
            "fguid" => $fguid,
        ]);
    }

    #[
        Route(
            "/s/{sguid}/f/{fguid}/read-stay",
            name: "feed_item_filtered_mark_read_stay",
            requirements: [
                "sguid" => "[a-f0-9]{16}",
                "fguid" => "[a-f0-9]{16}",
            ],
            methods: ["POST"],
        ),
    ]
    public function markAsReadFilteredStay(
        Request $request,
        string $sguid,
        string $fguid,
    ): Response {
        $this->validateCsrfToken($request, "mark_read");
        $user = $this->userService->getCurrentUser();
        $this->readStatusService->markAsRead($user->getId(), $fguid);

        return $this->redirectToRoute("feed_item_filtered", [
            "sguid" => $sguid,
            "fguid" => $fguid,
        ]);
    }

    private function renderFeedView(
        Request $request,
        ?string $sguid = null,
        ?string $fguid = null,
    ): Response {
        $user = $this->userService->getCurrentUser();
        $unread = $request->query->getBoolean("unread", false);
        $limit = $request->query->getInt("limit", 50);

        $viewData = $this->feedViewService->getViewData(
            $user->getId(),
            $sguid,
            $fguid,
            $unread,
            $limit,
        );

        if ($viewData["activeItem"]) {
            $this->seenStatusService->markAsSeen(
                $user->getId(),
                $viewData["activeItem"]["guid"],
            );
        }

        return $this->render("feed/index.html.twig", [
            "feeds" => $viewData["feeds"],
            "items" => $viewData["items"],
            "allItemsCount" => $viewData["allItemsCount"],
            "activeItem" => $viewData["activeItem"],
            "activeFeed" => $sguid,
            "unread" => $unread,
            "limit" => $limit,
        ]);
    }

    private function validateCsrfToken(Request $request, string $tokenId): void
    {
        $token = new CsrfToken($tokenId, $request->request->get("_token"));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException("Invalid CSRF token.");
        }
    }
}
