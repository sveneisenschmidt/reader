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
        $conn = $this->getEntityManager()->getConnection();
        $conn->executeStatement(
            'INSERT OR IGNORE INTO seen_status (user_id, feed_item_guid, seen_at) VALUES (?, ?, ?)',
            [
                $userId,
                $feedItemGuid,
                new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );
    }

    #[Param(feedItemGuids: 'list<string>')]
    public function markManyAsSeen(int $userId, array $feedItemGuids): void
    {
        if (empty($feedItemGuids)) {
            return;
        }

        $conn = $this->getEntityManager()->getConnection();
        $now = new \DateTimeImmutable()->format('Y-m-d H:i:s');

        $placeholders = [];
        $params = [];
        foreach ($feedItemGuids as $guid) {
            $placeholders[] = '(?, ?, ?)';
            $params[] = $userId;
            $params[] = $guid;
            $params[] = $now;
        }

        $sql =
            'INSERT OR IGNORE INTO seen_status (user_id, feed_item_guid, seen_at) VALUES '.
            implode(', ', $placeholders);
        $conn->executeStatement($sql, $params);
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
