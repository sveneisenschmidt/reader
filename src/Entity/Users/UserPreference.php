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
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $preferenceKey;

    #[ORM\Column(type: 'text')]
    private string $value;

    public function __construct(
        int $userId,
        string $preferenceKey,
        string $value = '0',
    ) {
        $this->userId = $userId;
        $this->preferenceKey = $preferenceKey;
        $this->value = $value;
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
        return $this->value === '1';
    }

    public function setEnabled(bool $isEnabled): self
    {
        $this->value = $isEnabled ? '1' : '0';

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): self
    {
        $this->value = $value;

        return $this;
    }
}
