<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Users;

use App\Entity\Users\UserPreference;
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

    public function isEnabled(int $userId, string $preferenceKey): bool
    {
        $preference = $this->findOneBy([
            'userId' => $userId,
            'preferenceKey' => $preferenceKey,
        ]);

        return $preference?->isEnabled() ?? false;
    }

    public function setEnabled(int $userId, string $preferenceKey, bool $isEnabled): void
    {
        $preference = $this->findOneBy([
            'userId' => $userId,
            'preferenceKey' => $preferenceKey,
        ]);

        if ($preference === null) {
            $preference = new UserPreference($userId, $preferenceKey, $isEnabled);
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
}
