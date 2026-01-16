<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository;

use App\Entity\BookmarkStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', BookmarkStatus::class)]
class BookmarkStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookmarkStatus::class);
    }

    public function bookmark(int $userId, string $feedItemGuid): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $conn->executeStatement(
            'INSERT OR IGNORE INTO bookmark_status (user_id, feed_item_guid, bookmarked_at) VALUES (?, ?, ?)',
            [
                $userId,
                $feedItemGuid,
                new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function unbookmark(int $userId, string $feedItemGuid): void
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

    public function isBookmarked(int $userId, string $feedItemGuid): bool
    {
        return $this->findOneBy([
            'userId' => $userId,
            'feedItemGuid' => $feedItemGuid,
        ]) !== null;
    }

    #[Returns('list<string>')]
    public function getBookmarkedGuidsForUser(int $userId): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('b.feedItemGuid')
            ->where('b.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'feedItemGuid');
    }

    public function countByUser(int $userId): int
    {
        return $this->count(['userId' => $userId]);
    }
}
