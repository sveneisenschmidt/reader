<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Users;

use App\Entity\Users\ReadStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReadStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadStatus::class);
    }

    public function markAsRead(int $userId, string $feedItemGuid): void
    {
        $existing = $this->findOneBy([
            'userId' => $userId,
            'feedItemGuid' => $feedItemGuid,
        ]);

        if ($existing === null) {
            $readStatus = new ReadStatus($userId, $feedItemGuid);
            $this->getEntityManager()->persist($readStatus);
            $this->getEntityManager()->flush();
        }
    }

    public function markAsUnread(int $userId, string $feedItemGuid): void
    {
        $existing = $this->findOneBy([
            'userId' => $userId,
            'feedItemGuid' => $feedItemGuid,
        ]);

        if ($existing !== null) {
            $this->getEntityManager()->remove($existing);
            $this->getEntityManager()->flush();
        }
    }

    public function isRead(int $userId, string $feedItemGuid): bool
    {
        return $this->findOneBy([
            'userId' => $userId,
            'feedItemGuid' => $feedItemGuid,
        ]) !== null;
    }

    public function getReadGuidsForUser(int $userId): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.feedItemGuid')
            ->where('r.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'feedItemGuid');
    }

    public function markManyAsRead(int $userId, array $feedItemGuids): void
    {
        if (empty($feedItemGuids)) {
            return;
        }

        $existingGuids = $this->createQueryBuilder('r')
            ->select('r.feedItemGuid')
            ->where('r.userId = :userId')
            ->andWhere('r.feedItemGuid IN (:guids)')
            ->setParameter('userId', $userId)
            ->setParameter('guids', $feedItemGuids)
            ->getQuery()
            ->getScalarResult();

        $existingGuids = array_column($existingGuids, 'feedItemGuid');
        $newGuids = array_diff($feedItemGuids, $existingGuids);

        $em = $this->getEntityManager();
        foreach ($newGuids as $guid) {
            $readStatus = new ReadStatus($userId, $guid);
            $em->persist($readStatus);
        }

        $em->flush();
    }

    public function deleteByFeedItemGuids(
        int $userId,
        array $feedItemGuids,
    ): int {
        if (empty($feedItemGuids)) {
            return 0;
        }

        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.userId = :userId')
            ->andWhere('r.feedItemGuid IN (:guids)')
            ->setParameter('userId', $userId)
            ->setParameter('guids', $feedItemGuids)
            ->getQuery()
            ->execute();
    }
}
