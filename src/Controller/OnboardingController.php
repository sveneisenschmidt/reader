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
    ) {
    }

    #[Route('/onboarding', name: 'onboarding')]
    public function index(Request $request): Response
    {
        $user = $this->userService->getCurrentUser();

        if ($this->subscriptionService->hasSubscriptions($user->getId())) {
            return $this->redirectToRoute('feed_index');
        }

        $form = $this->createForm(FirstFeedType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $feedUrl = $data['feedUrl'];

            $error = $this->feedFetcher->validateFeedUrl($feedUrl);
            if ($error !== null) {
                $form
                    ->get('feedUrl')
                    ->addError(new \Symfony\Component\Form\FormError($error));
            } else {
                $user = $this->userService->getCurrentUser();

                $this->subscriptionService->addSubscription(
                    $user->getId(),
                    $feedUrl,
                );
                $this->feedFetcher->refreshAllFeeds([$feedUrl]);
                $this->subscriptionService->updateRefreshTimestamps(
                    $user->getId(),
                );

                return $this->redirectToRoute('feed_index');
            }
        }

        return $this->render('onboarding.html.twig', [
            'form' => $form,
        ]);
    }
}
