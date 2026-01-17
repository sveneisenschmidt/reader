<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use App\Domain\User\Repository\UserRepository;
use App\Domain\User\Service\TotpService;
use App\Domain\User\Service\UserRegistrationService;
use App\Form\LoginType;
use App\Form\SetupType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserRegistrationService $registrationService,
        private TotpService $totpService,
    ) {
    }

    #[Route('/login', name: 'auth_login')]
    public function login(Request $request): Response
    {
        if (!$this->userRepository->hasAnyUser()) {
            return $this->redirectToRoute('auth_setup');
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('feed_index');
        }

        $form = $this->createForm(LoginType::class);

        $error = $request->getSession()->get('auth_error');
        $request->getSession()->remove('auth_error');

        return $this->render('auth/login.html.twig', [
            'form' => $form,
            'error' => $error,
        ]);
    }

    #[Route('/setup', name: 'auth_setup')]
    public function setup(Request $request): Response
    {
        if ($this->userRepository->hasAnyUser()) {
            return $this->redirectToRoute('feed_index');
        }

        $totpSecret = $this->getOrCreateTotpSecret($request);
        $form = $this->createForm(SetupType::class);
        $form->handleRequest($request);

        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if (!$this->totpService->verify($totpSecret, $data['otp'])) {
                $error =
                    'Invalid OTP code. Please scan the QR code and try again.';
            } else {
                $this->registrationService->register(
                    $data['email'],
                    $data['password'],
                    $totpSecret,
                );
                $request->getSession()->remove('setup_totp_secret');

                return $this->redirectToRoute('auth_login');
            }
        }

        return $this->render('auth/setup.html.twig', [
            'form' => $form,
            'error' => $error,
            'totp_secret' => $totpSecret,
            'totp_qr_data_uri' => $this->totpService->getQrCodeDataUri(
                $totpSecret,
            ),
        ]);
    }

    #[Route('/logout', name: 'auth_logout')]
    #[\PHPUnit\Framework\Attributes\CodeCoverageIgnore]
    public function logout(): never
    {
        throw new \LogicException('This should never be reached.');
    }

    private function getOrCreateTotpSecret(Request $request): string
    {
        $totpSecret = $request->getSession()->get('setup_totp_secret');

        if (!$totpSecret) {
            $totpSecret = $this->totpService->generateSecret();
            $request->getSession()->set('setup_totp_secret', $totpSecret);
        }

        return $totpSecret;
    }
}
