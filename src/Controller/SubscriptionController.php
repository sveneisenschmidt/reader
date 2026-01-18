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
use App\Form\SubscriptionsType;
use App\Service\OpmlService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private UserService $userService,
        private FeedResolverInterface $feedResolver,
        private OpmlService $opmlService,
    ) {
    }

    #[Route('/subscriptions', name: 'subscriptions')]
    public function index(Request $request): Response
    {
        $user = $this->userService->getCurrentUser();
        $subscriptions = $this->subscriptionService->getSubscriptionsForUser(
            $user->getId(),
        );

        $existingData = [];
        foreach ($subscriptions as $subscription) {
            $existingData[] = [
                'guid' => $subscription->getGuid(),
                'url' => $subscription->getUrl(),
                'name' => $subscription->getName(),
                'folder' => $subscription->getFolder(),
                'useArchiveIs' => $subscription->getUseArchiveIs(),
            ];
        }

        $form = $this->createForm(SubscriptionsType::class, [
            'existing' => $existingData,
            'new' => ['guid' => '', 'url' => '', 'name' => ''],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Check if a remove or save button was clicked for a specific subscription
            foreach ($form->get('existing') as $index => $subscriptionForm) {
                if ($subscriptionForm->get('remove')->isClicked()) {
                    $guid = $data['existing'][$index]['guid'];
                    $this->subscriptionService->removeSubscription(
                        $user->getId(),
                        $guid,
                    );
                    $this->addFlash('success', 'Feed removed.');

                    return $this->redirectToRoute('subscriptions');
                }

                if ($subscriptionForm->get('save')->isClicked()) {
                    $item = $data['existing'][$index];
                    $this->subscriptionService->updateSubscription(
                        $user->getId(),
                        $item['guid'],
                        $item['name'],
                        $item['folder'] ?? null,
                        $item['useArchiveIs'] ?? false,
                    );
                    $this->addFlash('success', 'Feed updated.');

                    return $this->redirectToRoute('subscriptions');
                }
            }

            // Handle OPML import
            if ($form->get('import')->isClicked()) {
                $file = $form->get('opml')->getData();

                if ($file === null) {
                    $this->addFlash('error', 'No file uploaded.');

                    return $this->redirectToRoute('subscriptions');
                }

                $content = file_get_contents($file->getPathname());
                $feeds = $this->opmlService->parse($content);

                if (empty($feeds)) {
                    $this->addFlash('error', 'No feeds found in file.');

                    return $this->redirectToRoute('subscriptions');
                }

                $imported = 0;
                $skipped = 0;

                foreach ($feeds as $feed) {
                    $result = $this->feedResolver->resolve($feed['url']);

                    if ($result->getError() !== null) {
                        ++$skipped;

                        continue;
                    }

                    $feedUrl = $result->getFeedUrl();

                    // Check for duplicates
                    $existingUrls = array_map(
                        fn ($s) => $s->getUrl(),
                        $subscriptions,
                    );

                    if (in_array($feedUrl, $existingUrls)) {
                        ++$skipped;

                        continue;
                    }

                    $subscription = $this->subscriptionService->addSubscription(
                        $user->getId(),
                        $feedUrl,
                    );

                    // Update folder if provided
                    if (!empty($feed['folder'])) {
                        $this->subscriptionService->updateSubscription(
                            $user->getId(),
                            $subscription->getGuid(),
                            $subscription->getName(),
                            $feed['folder'],
                            $subscription->getUseArchiveIs(),
                        );
                    }

                    $this->subscriptionService->updateRefreshTimestamp(
                        $subscription,
                    );
                    ++$imported;

                    // Update subscriptions list for duplicate check
                    $subscriptions = $this->subscriptionService->getSubscriptionsForUser(
                        $user->getId(),
                    );
                }

                if ($imported > 0) {
                    $this->addFlash(
                        'success',
                        sprintf('%d feed(s) imported.', $imported),
                    );
                }
                if ($skipped > 0) {
                    $this->addFlash(
                        'success',
                        sprintf(
                            '%d feed(s) skipped (duplicates or errors).',
                            $skipped,
                        ),
                    );
                }

                return $this->redirectToRoute('subscriptions');
            }

            $hasError = false;

            // Handle new subscription
            $newData = $data['new'];
            if (!empty($newData['url'])) {
                $result = $this->feedResolver->resolve($newData['url']);

                if ($result->getError() !== null) {
                    $form
                        ->get('new')
                        ->get('url')
                        ->addError(new FormError($result->getError()));
                    $hasError = true;
                } else {
                    $feedUrl = $result->getFeedUrl();

                    // Check if feed already exists
                    $existingUrls = array_map(
                        fn ($s) => $s->getUrl(),
                        $subscriptions,
                    );
                    if (in_array($feedUrl, $existingUrls)) {
                        $form
                            ->get('new')
                            ->get('url')
                            ->addError(
                                new FormError(
                                    'This feed is already subscribed.',
                                ),
                            );
                        $hasError = true;
                    } else {
                        $subscription = $this->subscriptionService->addSubscription(
                            $user->getId(),
                            $feedUrl,
                        );
                        // Feed content is already fetched by addSubscription (via getFeedTitle)
                        // Just update the refresh timestamp for this subscription
                        $this->subscriptionService->updateRefreshTimestamp(
                            $subscription,
                        );
                        $this->addFlash('success', 'Feed added.');
                    }
                }
            }

            if (!$hasError) {
                return $this->redirectToRoute('subscriptions');
            }
        }

        return $this->render('subscription/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[
        Route(
            '/subscriptions/export',
            name: 'subscription_export',
            methods: ['GET'],
        ),
    ]
    public function export(): Response
    {
        $user = $this->userService->getCurrentUser();
        $subscriptions = $this->subscriptionService->getSubscriptionsForUser(
            $user->getId(),
        );

        $content = $this->opmlService->generate($subscriptions);

        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/xml');
        $response->headers->set(
            'Content-Disposition',
            'attachment; filename="reader-subscriptions.opml"',
        );

        return $response;
    }
}
