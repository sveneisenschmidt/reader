<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Entity\Logs\LogEntry;
use App\Entity\Users\User;
use App\Form\ProfileType;
use App\Repository\Logs\LogEntryRepository;
use App\Service\WorkerHeartbeat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted("ROLE_USER")]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkerHeartbeat $workerHeartbeat,
        private LogEntryRepository $logEntryRepository,
    ) {}

    #[Route("/profile", name: "profile")]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, [
            "username" => $user->getUsername(),
            "theme" => $user->getTheme(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $user->setUsername($data["username"]);
            $user->setTheme($data["theme"]);

            $this->entityManager->flush();

            $this->addFlash("success", "Profile updated.");

            return $this->redirectToRoute("profile");
        }

        $lastWebhook = $this->logEntryRepository->getLastByChannel(
            LogEntry::CHANNEL_WEBHOOK,
        );
        $webhookAlive =
            $lastWebhook &&
            $lastWebhook->getStatus() === LogEntry::STATUS_SUCCESS &&
            $lastWebhook->getCreatedAt()->getTimestamp() > time() - 300;

        return $this->render("profile/index.html.twig", [
            "form" => $form,
            "email" => $user->getEmail(),
            "workerAlive" => $this->workerHeartbeat->isAlive(15),
            "workerLastBeat" => $this->workerHeartbeat->getLastBeat(),
            "lastWebhook" => $lastWebhook,
            "webhookAlive" => $webhookAlive,
        ]);
    }
}
