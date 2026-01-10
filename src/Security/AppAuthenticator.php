<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Security;

use App\Repository\Users\UserRepository;
use App\Service\EncryptionService;
use App\Service\TotpService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AppAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TotpService $totpService,
        private EncryptionService $totpEncryption,
        #[
            Autowire(service: 'limiter.login'),
        ]
        private RateLimiterFactory $loginLimiter,
        private RouterInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/login'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $limiter = $this->loginLimiter->create($request->getClientIp());

        if (!$limiter->consume()->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Too many login attempts. Please try again later.');
        }

        $loginData = $request->request->all('login');
        $email = $loginData['email'] ?? '';
        $password = $loginData['password'] ?? '';
        $otpCode = $loginData['otp'] ?? '';

        if (empty($email) || empty($password) || empty($otpCode)) {
            throw new CustomUserMessageAuthenticationException('All fields are required.');
        }

        $user = $this->userRepository->findByEmail($email);

        if (
            !$user
            || !$this->passwordHasher->isPasswordValid($user, $password)
        ) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        $totpSecret = $user->getTotpSecret();

        if (!$totpSecret) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        $totpSecret = $this->totpEncryption->decrypt($totpSecret);

        if (!$this->totpService->verify($totpSecret, $otpCode)) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        $limiter->reset();

        return new SelfValidatingPassport(new UserBadge($email));
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName,
    ): ?Response {
        return new RedirectResponse($this->router->generate('feed_index'));
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception,
    ): ?Response {
        $request->getSession()->set('auth_error', $exception->getMessage());

        return new RedirectResponse($this->router->generate('auth_login'));
    }

    public function start(
        Request $request,
        ?AuthenticationException $authException = null,
    ): Response {
        if (!$this->userRepository->hasAnyUser()) {
            return new RedirectResponse($this->router->generate('auth_setup'));
        }

        return new RedirectResponse($this->router->generate('auth_login'));
    }
}
