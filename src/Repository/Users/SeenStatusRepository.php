<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Users;

use App\Entity\Users\SeenStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', SeenStatus::class)]
class SeenStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeenStatus::class);
    }

    public function markAsSeen(int $userId, string $feedItemGuid): void
    {
        $existing = $this->findOneBy([
            'userId' => $userId,
            'feedItemGuid' => $feedItemGuid,
        ]);

        if ($existing === null) {
            $seenStatus = new SeenStatus($userId, $feedItemGuid);
            $this->getEntityManager()->persist($seenStatus);
            $this->getEntityManager()->flush();
        }
    }

    #[Param(feedItemGuids: 'list<string>')]
    public function markManyAsSeen(int $userId, array $feedItemGuids): void
    {
        $existingGuids = $this->getSeenGuidsForUser($userId, $feedItemGuids);
        $newGuids = array_diff($feedItemGuids, $existingGuids);

        foreach ($newGuids as $guid) {
            $seenStatus = new SeenStatus($userId, $guid);
            $this->getEntityManager()->persist($seenStatus);
        }

        if (count($newGuids) > 0) {
            $this->getEntityManager()->flush();
        }
    }

    public function isSeen(int $userId, string $feedItemGuid): bool
    {
        return $this->findOneBy([
            'userId' => $userId,
            'feedItemGuid' => $feedItemGuid,
        ]) !== null;
    }

    #[Param(filterGuids: 'list<string>')]
    #[Returns('list<string>')]
    public function getSeenGuidsForUser(
        int $userId,
        array $filterGuids = [],
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->select('s.feedItemGuid')
            ->where('s.userId = :userId')
            ->setParameter('userId', $userId);

        if (count($filterGuids) > 0) {
            $qb->andWhere('s.feedItemGuid IN (:guids)')->setParameter(
                'guids',
                $filterGuids,
            );
        }

        $results = $qb->getQuery()->getScalarResult();

        return array_column($results, 'feedItemGuid');
    }

    #[Param(feedItemGuids: 'list<string>')]
    public function deleteByFeedItemGuids(
        int $userId,
        array $feedItemGuids,
    ): int {
        if (empty($feedItemGuids)) {
            return 0;
        }

        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.userId = :userId')
            ->andWhere('s.feedItemGuid IN (:guids)')
            ->setParameter('userId', $userId)
            ->setParameter('guids', $feedItemGuids)
            ->getQuery()
            ->execute();
    }
}
