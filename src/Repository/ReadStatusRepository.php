<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository;

use App\Entity\ReadStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', ReadStatus::class)]
class ReadStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadStatus::class);
    }

    public function markAsRead(int $userId, string $feedItemGuid): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->executeStatement(
            'INSERT OR IGNORE INTO read_status (user_id, feed_item_guid, read_at) VALUES (?, ?, ?)',
            [
                $userId,
                $feedItemGuid,
                new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );
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

    #[Returns('list<string>')]
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

    #[Param(feedItemGuids: 'list<string>')]
    public function markManyAsRead(int $userId, array $feedItemGuids): void
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
            'INSERT OR IGNORE INTO read_status (user_id, feed_item_guid, read_at) VALUES '.
            implode(', ', $placeholders);
        $conn->executeStatement($sql, $params);
    }

    #[Param(feedItemGuids: 'list<string>')]
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
