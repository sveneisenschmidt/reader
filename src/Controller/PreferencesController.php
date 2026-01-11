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
use App\Entity\Users\UserPreference;
use App\Form\PreferencesType;
use App\Form\ProfileType;
use App\Service\StatusIndicator;
use App\Service\UserPreferenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PreferencesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StatusIndicator $statusIndicator,
        private UserPreferenceService $userPreferenceService,
    ) {
    }

    #[Route('/preferences', name: 'preferences')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = (int) $user->getId();

        $userPrefs = $this->userPreferenceService->getAllPreferences($userId);

        $profileForm = $this->createForm(ProfileType::class, [
            'username' => $user->getUsername(),
        ]);

        $preferencesForm = $this->createForm(PreferencesType::class, [
            'theme' => $user->getTheme(),
            'showNextUnread' => $userPrefs[UserPreference::SHOW_NEXT_UNREAD],
            'pullToRefresh' => $userPrefs[UserPreference::PULL_TO_REFRESH],
            'filterWords' => $userPrefs[UserPreference::FILTER_WORDS],
        ]);

        $profileForm->handleRequest($request);
        $preferencesForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $data = $profileForm->getData();
            $user->setUsername($data['username']);
            $this->entityManager->flush();
            $this->addFlash('success', 'Profile saved.');

            return $this->redirectToRoute('preferences');
        }

        if ($preferencesForm->isSubmitted() && $preferencesForm->isValid()) {
            $data = $preferencesForm->getData();
            $user->setTheme($data['theme']);
            $this->entityManager->flush();

            $this->userPreferenceService->setShowNextUnread(
                $userId,
                $data['showNextUnread'],
            );
            $this->userPreferenceService->setPullToRefresh(
                $userId,
                $data['pullToRefresh'],
            );
            $this->userPreferenceService->setFilterWords(
                $userId,
                $data['filterWords'] ?? '',
            );

            $this->addFlash('success', 'Preferences saved.');

            return $this->redirectToRoute('preferences');
        }

        return $this->render('preferences/index.html.twig', [
            'profileForm' => $profileForm,
            'preferencesForm' => $preferencesForm,
            'email' => $user->getEmail(),
            'workerAlive' => $this->statusIndicator->isWorkerAlive(),
            'workerLastBeat' => $this->statusIndicator->getWorkerLastBeat(),
            'webhookAlive' => $this->statusIndicator->isWebhookAlive(),
            'webhookLastBeat' => $this->statusIndicator->getWebhookLastBeat(),
        ]);
    }
}
