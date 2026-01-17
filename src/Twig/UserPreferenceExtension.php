<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

use App\Domain\User\Entity\User;
use App\Domain\User\Service\UserPreferenceService;
use App\Enum\PreferenceDefault;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UserPreferenceExtension extends AbstractExtension
{
    public function __construct(
        private UserPreferenceService $userPreferenceService,
        private Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_theme', [$this, 'getTheme']),
            new TwigFunction('user_keyboard_shortcuts', [$this, 'hasKeyboardShortcuts']),
            new TwigFunction('user_auto_mark_read', [$this, 'hasAutoMarkRead']),
        ];
    }

    public function getTheme(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return PreferenceDefault::Theme->value();
        }

        return $this->userPreferenceService->getTheme($user->getId());
    }

    public function hasKeyboardShortcuts(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return PreferenceDefault::KeyboardShortcuts->asBool();
        }

        return $this->userPreferenceService->isKeyboardShortcutsEnabled($user->getId());
    }

    public function hasAutoMarkRead(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return PreferenceDefault::AutoMarkRead->asBool();
        }

        return $this->userPreferenceService->isAutoMarkReadEnabled($user->getId());
    }
}
