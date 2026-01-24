<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\User\Service;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use App\Service\EncryptionService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EncryptionService $totpEncryption,
        private UsernameGenerator $usernameGenerator,
    ) {
    }

    public function register(
        string $email,
        string $password,
        string $totpSecret,
    ): User {
        $username = $this->usernameGenerator->generate();
        $user = new User($username);
        $user->setEmail($email);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password),
        );
        $user->setTotpSecret($this->totpEncryption->encrypt($totpSecret));

        $this->userRepository->save($user);

        return $user;
    }
}
