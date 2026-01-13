<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use App\Enum\PreferenceDefault;
use App\Enum\PreferenceKey;
use App\Repository\Users\UserPreferenceRepository;
use PhpStaticAnalysis\Attributes\Returns;

class UserPreferenceService
{
    public function __construct(
        private UserPreferenceRepository $userPreferenceRepository,
    ) {
    }

    public function getTheme(int $userId): string
    {
        return $this->userPreferenceRepository->getValue(
            $userId,
            PreferenceKey::Theme,
            PreferenceDefault::Theme->value(),
        );
    }

    public function setTheme(int $userId, string $theme): void
    {
        $this->userPreferenceRepository->setValue(
            $userId,
            PreferenceKey::Theme,
            $theme,
        );
    }

    public function isPullToRefreshEnabled(int $userId): bool
    {
        return $this->userPreferenceRepository->isEnabled(
            $userId,
            PreferenceKey::PullToRefresh,
            PreferenceDefault::PullToRefresh->asBool(),
        );
    }

    public function setPullToRefresh(int $userId, bool $enabled): void
    {
        $this->userPreferenceRepository->setEnabled(
            $userId,
            PreferenceKey::PullToRefresh,
            $enabled,
        );
    }

    public function isAutoMarkReadEnabled(int $userId): bool
    {
        return $this->userPreferenceRepository->isEnabled(
            $userId,
            PreferenceKey::AutoMarkRead,
            PreferenceDefault::AutoMarkRead->asBool(),
        );
    }

    public function setAutoMarkRead(int $userId, bool $enabled): void
    {
        $this->userPreferenceRepository->setEnabled(
            $userId,
            PreferenceKey::AutoMarkRead,
            $enabled,
        );
    }

    public function isKeyboardShortcutsEnabled(int $userId): bool
    {
        return $this->userPreferenceRepository->isEnabled(
            $userId,
            PreferenceKey::KeyboardShortcuts,
            PreferenceDefault::KeyboardShortcuts->asBool(),
        );
    }

    public function setKeyboardShortcuts(int $userId, bool $enabled): void
    {
        $this->userPreferenceRepository->setEnabled(
            $userId,
            PreferenceKey::KeyboardShortcuts,
            $enabled,
        );
    }

    #[Returns('list<string>')]
    public function getFilterWords(int $userId): array
    {
        $raw = $this->userPreferenceRepository->getValue(
            $userId,
            PreferenceKey::FilterWords,
            PreferenceDefault::FilterWords->value(),
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
            PreferenceKey::FilterWords,
            PreferenceDefault::FilterWords->value(),
        );
    }

    public function setFilterWords(int $userId, string $words): void
    {
        $this->userPreferenceRepository->setValue(
            $userId,
            PreferenceKey::FilterWords,
            $words,
        );
    }

    #[Returns('array<string, mixed>')]
    public function getAllPreferences(int $userId): array
    {
        return [
            PreferenceKey::Theme->value => $this->getTheme($userId),
            PreferenceKey::PullToRefresh
                ->value => $this->isPullToRefreshEnabled($userId),
            PreferenceKey::AutoMarkRead->value => $this->isAutoMarkReadEnabled(
                $userId,
            ),
            PreferenceKey::KeyboardShortcuts
                ->value => $this->isKeyboardShortcutsEnabled($userId),
            PreferenceKey::FilterWords->value => $this->getFilterWordsRaw(
                $userId,
            ),
        ];
    }
}
