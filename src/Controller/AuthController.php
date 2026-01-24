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
use App\Form\ResetPasswordType;
use App\Form\SetupType;
use App\Form\TotpType;
use App\Service\EncryptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
    public function logout(): never
    {
        throw new \LogicException('This should never be reached.');
    }

    #[Route('/password', name: 'auth_password', methods: ['GET', 'POST'])]
    public function password(
        Request $request,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof \App\Domain\User\Entity\User) {
            return $this->redirectToRoute('auth_login');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $totpSecret = $encryptionService->decrypt(
                $currentUser->getTotpSecret(),
            );
            $isValid = $this->totpService->verify($totpSecret, $data['otp']);

            if ($isValid) {
                $hashedPassword = $passwordHasher->hashPassword(
                    $currentUser,
                    $data['password'],
                );
                $this->userRepository->upgradePassword(
                    $currentUser,
                    $hashedPassword,
                );

                $this->addFlash('success', 'Password has been updated.');

                return $this->redirectToRoute('preferences');
            }

            $error = 'Invalid verification code.';
        }

        return $this->render(
            'auth/password.html.twig',
            [
                'form' => $form,
                'error' => $error,
            ],
            new Response(
                status: $form->isSubmitted() && !$form->isValid() ? 422 : 200,
            ),
        );
    }

    #[Route('/totp', name: 'auth_totp', methods: ['GET', 'POST'])]
    public function totp(
        Request $request,
        EncryptionService $encryptionService,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof \App\Domain\User\Entity\User) {
            return $this->redirectToRoute('auth_login');
        }

        $newTotpSecret = $this->getOrCreateTotpSecret(
            $request,
            'totp_new_secret',
        );

        $form = $this->createForm(TotpType::class);
        $form->handleRequest($request);

        $error = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $isValid = $passwordHasher->isPasswordValid(
                $currentUser,
                $data['password'],
            );
            if ($isValid) {
                $currentTotpSecret = $encryptionService->decrypt(
                    $currentUser->getTotpSecret(),
                );
                $isValid = $this->totpService->verify(
                    $currentTotpSecret,
                    $data['current_otp'],
                    1,
                );
            }

            if ($isValid) {
                if (
                    $this->totpService->verify($newTotpSecret, $data['new_otp'])
                ) {
                    $currentUser->setTotpSecret(
                        $encryptionService->encrypt($newTotpSecret),
                    );
                    $this->userRepository->save($currentUser);

                    $request->getSession()->remove('totp_new_secret');
                    $this->addFlash(
                        'success',
                        'Authentication has been updated.',
                    );

                    return $this->redirectToRoute('preferences');
                }

                $error =
                    'Invalid new verification code. Please scan the QR code and try again.';
            } else {
                $error = 'Invalid credentials.';
            }
        }

        return $this->render(
            'auth/totp.html.twig',
            [
                'form' => $form,
                'error' => $error,
                'totp_secret' => $newTotpSecret,
                'totp_qr_data_uri' => $this->totpService->getQrCodeDataUri(
                    $newTotpSecret,
                ),
            ],
            new Response(
                status: $form->isSubmitted() && !$form->isValid() ? 422 : 200,
            ),
        );
    }

    private function getOrCreateTotpSecret(
        Request $request,
        string $sessionKey = 'setup_totp_secret',
    ): string {
        $totpSecret = $request->getSession()->get($sessionKey);

        if (!$totpSecret) {
            $totpSecret = $this->totpService->generateSecret();
            $request->getSession()->set($sessionKey, $totpSecret);
        }

        return $totpSecret;
    }
}
