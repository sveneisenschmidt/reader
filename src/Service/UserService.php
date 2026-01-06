<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */


namespace App\Service;

use App\Entity\Users\User;
use App\Repository\Users\UserRepository;

class UserService
{
    private const DUMMY_EMAIL = 'dummy@reader.local';

    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function getCurrentUser(): User
    {
        return $this->userRepository->getOrCreateDummyUser();
    }

    public function isDummyUser(User $user): bool
    {
        return $user->getEmail() === self::DUMMY_EMAIL;
    }
}
