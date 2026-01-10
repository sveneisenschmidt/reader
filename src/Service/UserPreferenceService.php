<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Entity\Users\UserPreference;
use App\Repository\Users\UserPreferenceRepository;
use PhpStaticAnalysis\Attributes\Returns;

class UserPreferenceService
{
    public function __construct(
        private UserPreferenceRepository $userPreferenceRepository,
    ) {
    }

    public function isShowNextUnreadEnabled(int $userId): bool
    {
        return $this->userPreferenceRepository->isEnabled(
            $userId,
            UserPreference::SHOW_NEXT_UNREAD,
        );
    }

    public function setShowNextUnread(int $userId, bool $enabled): void
    {
        $this->userPreferenceRepository->setEnabled(
            $userId,
            UserPreference::SHOW_NEXT_UNREAD,
            $enabled,
        );
    }

    public function isAutoMarkAsReadEnabled(int $userId): bool
    {
        return $this->userPreferenceRepository->isEnabled(
            $userId,
            UserPreference::AUTO_MARK_AS_READ,
        );
    }

    public function setAutoMarkAsRead(int $userId, bool $enabled): void
    {
        $this->userPreferenceRepository->setEnabled(
            $userId,
            UserPreference::AUTO_MARK_AS_READ,
            $enabled,
        );
    }

    public function isPullToRefreshEnabled(int $userId): bool
    {
        return $this->userPreferenceRepository->isEnabled(
            $userId,
            UserPreference::PULL_TO_REFRESH,
            true,
        );
    }

    public function setPullToRefresh(int $userId, bool $enabled): void
    {
        $this->userPreferenceRepository->setEnabled(
            $userId,
            UserPreference::PULL_TO_REFRESH,
            $enabled,
        );
    }

    #[Returns('array<string, bool>')]
    public function getAllPreferences(int $userId): array
    {
        $prefs = $this->userPreferenceRepository->getAllForUser($userId);

        return [
            UserPreference::SHOW_NEXT_UNREAD => $prefs[UserPreference::SHOW_NEXT_UNREAD] ?? false,
            UserPreference::AUTO_MARK_AS_READ => $prefs[UserPreference::AUTO_MARK_AS_READ] ?? false,
            UserPreference::PULL_TO_REFRESH => $prefs[UserPreference::PULL_TO_REFRESH] ?? true,
        ];
    }
}
