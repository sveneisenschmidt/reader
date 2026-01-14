<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository;

use App\Entity\UserPreference;
use App\Enum\PreferenceKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', UserPreference::class)]
class UserPreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPreference::class);
    }

    public function isEnabled(
        int $userId,
        PreferenceKey $preferenceKey,
        bool $default = false,
    ): bool {
        $preference = $this->findOneBy([
            'userId' => $userId,
            'preferenceKey' => $preferenceKey->value,
        ]);

        return $preference?->isEnabled() ?? $default;
    }

    public function setEnabled(
        int $userId,
        PreferenceKey $preferenceKey,
        bool $isEnabled,
    ): void {
        $preference = $this->findOneBy([
            'userId' => $userId,
            'preferenceKey' => $preferenceKey->value,
        ]);

        if ($preference === null) {
            $preference = new UserPreference(
                $userId,
                $preferenceKey->value,
                $isEnabled ? '1' : '0',
            );
            $this->getEntityManager()->persist($preference);
        } else {
            $preference->setEnabled($isEnabled);
        }

        $this->getEntityManager()->flush();
    }

    #[Returns('array<string, bool>')]
    public function getAllForUser(int $userId): array
    {
        $preferences = $this->findBy(['userId' => $userId]);

        $result = [];
        foreach ($preferences as $preference) {
            $result[$preference->getPreferenceKey()] = $preference->isEnabled();
        }

        return $result;
    }

    public function getValue(
        int $userId,
        PreferenceKey $preferenceKey,
        string $default = '',
    ): string {
        $preference = $this->findOneBy([
            'userId' => $userId,
            'preferenceKey' => $preferenceKey->value,
        ]);

        return $preference?->getValue() ?? $default;
    }

    public function setValue(
        int $userId,
        PreferenceKey $preferenceKey,
        string $value,
    ): void {
        $preference = $this->findOneBy([
            'userId' => $userId,
            'preferenceKey' => $preferenceKey->value,
        ]);

        if ($preference === null) {
            $preference = new UserPreference(
                $userId,
                $preferenceKey->value,
                $value,
            );
            $this->getEntityManager()->persist($preference);
        } else {
            $preference->setValue($value);
        }

        $this->getEntityManager()->flush();
    }
}
