<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Entity\Users;

use App\Repository\Users\UserPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preference')]
#[
    ORM\UniqueConstraint(
        name: 'user_preference_key',
        columns: ['user_id', 'preference_key'],
    ),
]
class UserPreference
{
    public const SHOW_NEXT_UNREAD = 'show_next_unread';
    public const AUTO_MARK_AS_READ = 'auto_mark_as_read';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $preferenceKey;

    #[ORM\Column(type: 'boolean')]
    private bool $isEnabled;

    public function __construct(
        int $userId,
        string $preferenceKey,
        bool $isEnabled = false,
    ) {
        $this->userId = $userId;
        $this->preferenceKey = $preferenceKey;
        $this->isEnabled = $isEnabled;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getPreferenceKey(): string
    {
        return $this->preferenceKey;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }
}
