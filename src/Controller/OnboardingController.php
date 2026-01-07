<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Form\FirstFeedType;
use App\Service\FeedFetcher;
use App\Service\SubscriptionService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OnboardingController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private SubscriptionService $subscriptionService,
        private FeedFetcher $feedFetcher,
    ) {}

    #[Route("/onboarding", name: "onboarding")]
    public function index(Request $request): Response
    {
        $form = $this->createForm(FirstFeedType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $user = $this->userService->getCurrentUser();

            $this->subscriptionService->addSubscription(
                $user->getId(),
                $data["feedUrl"],
            );
            $this->feedFetcher->refreshAllFeeds([$data["feedUrl"]]);
            $this->subscriptionService->updateRefreshTimestamps($user->getId());

            return $this->redirectToRoute("feed_index");
        }

        return $this->render("onboarding.html.twig", [
            "form" => $form,
        ]);
    }
}
