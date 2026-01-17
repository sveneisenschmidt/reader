<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Domain\Discovery\FeedResolverInterface;
use App\Domain\Feed\Service\SubscriptionService;
use App\Domain\User\Service\UserService;
use App\Enum\MessageSource;
use App\Form\FirstFeedType;
use App\Message\RefreshFeedsMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class OnboardingController extends AbstractController
{
    public function __construct(
        private UserService $userService,
        private SubscriptionService $subscriptionService,
        private FeedResolverInterface $feedResolver,
        private MessageBusInterface $messageBus,
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
            $inputUrl = $data['feedUrl'];

            $result = $this->feedResolver->resolve($inputUrl);
            if ($result->getError() !== null) {
                $form
                    ->get('feedUrl')
                    ->addError(
                        new \Symfony\Component\Form\FormError(
                            $result->getError(),
                        ),
                    );
            } else {
                $feedUrl = $result->getFeedUrl();
                $user = $this->userService->getCurrentUser();

                $this->subscriptionService->addSubscription(
                    $user->getId(),
                    $feedUrl,
                );
                $this->messageBus->dispatch(
                    new RefreshFeedsMessage(MessageSource::Manual),
                );

                return $this->redirectToRoute('feed_index');
            }
        }

        return $this->render('onboarding.html.twig', [
            'form' => $form,
        ]);
    }
}
