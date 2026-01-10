<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Entity\Users\User;
use App\Service\StatusIndicator;
use App\Service\StatusService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class StatusController extends AbstractController
{
    public function __construct(
        private StatusService $statusService,
        private StatusIndicator $statusIndicator,
    ) {
    }

    #[Route('/status', name: 'status')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = (int) $user->getId();

        $subscriptionStats = $this->statusService->getSubscriptionStats(
            $userId,
        );
        $messageCounts = $this->statusService->getProcessedMessageCountsBySource();

        return $this->render('status/index.html.twig', [
            'subscriptions' => $subscriptionStats['subscriptions'],
            'totals' => $subscriptionStats['totals'],
            'messageCountsBySource' => $messageCounts,
            'workerAlive' => $this->statusIndicator->isWorkerAlive(),
            'workerLastBeat' => $this->statusIndicator->getWorkerLastBeat(),
            'webhookAlive' => $this->statusIndicator->isWebhookAlive(),
            'webhookLastBeat' => $this->statusIndicator->getWebhookLastBeat(),
        ]);
    }
}
