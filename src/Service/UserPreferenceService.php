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

    #[Returns('list<string>')]
    public function getFilterWords(int $userId): array
    {
        $raw = $this->userPreferenceRepository->getValue(
            $userId,
            UserPreference::FILTER_WORDS,
            '',
        );

        return array_filter(
            array_map('trim', explode("\n", $raw)),
            fn ($w) => $w !== '',
        );
    }

    public function getFilterWordsRaw(int $userId): string
    {
        return $this->userPreferenceRepository->getValue(
            $userId,
            UserPreference::FILTER_WORDS,
            '',
        );
    }

    public function setFilterWords(int $userId, string $words): void
    {
        $this->userPreferenceRepository->setValue(
            $userId,
            UserPreference::FILTER_WORDS,
            $words,
        );
    }

    #[Returns('array<string, mixed>')]
    public function getAllPreferences(int $userId): array
    {
        return [
            UserPreference::SHOW_NEXT_UNREAD => $this->isShowNextUnreadEnabled(
                $userId,
            ),
            UserPreference::PULL_TO_REFRESH => $this->isPullToRefreshEnabled(
                $userId,
            ),
            UserPreference::FILTER_WORDS => $this->getFilterWordsRaw($userId),
        ];
    }
}
