<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Form\SubscriptionsType;
use App\Service\SubscriptionService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private UserService $userService,
    ) {}

    #[Route("/subscriptions", name: "subscriptions")]
    public function index(Request $request): Response
    {
        $user = $this->userService->getCurrentUser();
        $yaml = $this->subscriptionService->toYaml($user->getId());

        $form = $this->createForm(SubscriptionsType::class, ["yaml" => $yaml]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->subscriptionService->importFromYaml(
                    $user->getId(),
                    $data["yaml"],
                );
                $this->addFlash("success", "Subscriptions saved.");

                return $this->redirectToRoute("subscriptions");
            } catch (\InvalidArgumentException $e) {
                $this->addFlash("error", $e->getMessage());
            }
        }

        return $this->render("subscription/index.html.twig", [
            "form" => $form,
        ]);
    }
}
