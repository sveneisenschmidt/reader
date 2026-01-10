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
use App\Form\PreferencesType;
use App\Form\ProfileType;
use App\Service\StatusIndicator;
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
    ) {
    }

    #[Route('/preferences', name: 'preferences')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $profileForm = $this->createForm(ProfileType::class, [
            'username' => $user->getUsername(),
        ]);

        $preferencesForm = $this->createForm(PreferencesType::class, [
            'theme' => $user->getTheme(),
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
