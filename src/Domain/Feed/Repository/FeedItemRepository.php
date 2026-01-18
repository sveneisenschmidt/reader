<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Domain\Feed\Repository;

use App\Domain\Feed\Entity\FeedItem;
use App\Domain\ItemStatus\Entity\BookmarkStatus;
use App\Domain\ItemStatus\Entity\ReadStatus;
use App\Domain\ItemStatus\Entity\SeenStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', FeedItem::class)]
class FeedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedItem::class);
    }

    public function findByGuid(string $guid): ?FeedItem
    {
        return $this->findOneBy(['guid' => $guid]);
    }

    #[Param(guids: 'list<string>')]
    #[Returns('array<string, FeedItem>')]
    public function findByGuids(array $guids): array
    {
        if (empty($guids)) {
            return [];
        }

        $items = $this->createQueryBuilder('f')
            ->where('f.guid IN (:guids)')
            ->setParameter('guids', $guids)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($items as $item) {
            $result[$item->getGuid()] = $item;
        }

        return $result;
    }

    #[Param(subscriptionGuids: 'list<string>')]
    #[Returns('list<FeedItem>')]
    public function findBySubscriptionGuids(array $subscriptionGuids): array
    {
        if (empty($subscriptionGuids)) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->where('f.subscriptionGuid IN (:subscriptionGuids)')
            ->setParameter('subscriptionGuids', $subscriptionGuids)
            ->orderBy('f.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function upsert(FeedItem $feedItem): void
    {
        $existing = $this->findByGuid($feedItem->getGuid());

        if ($existing !== null) {
            $existing->setTitle($feedItem->getTitle());
            $existing->setLink($feedItem->getLink());
            $existing->setSource($feedItem->getSource());
            $existing->setExcerpt($feedItem->getExcerpt());
        } else {
            $this->getEntityManager()->persist($feedItem);
        }

        $this->getEntityManager()->flush();
    }

    #[Param(feedItems: 'list<FeedItem>')]
    public function upsertBatch(array $feedItems): void
    {
        if (empty($feedItems)) {
            return;
        }

        $guids = array_map(fn ($item) => $item->getGuid(), $feedItems);
        $existingItems = $this->findByGuids($guids);

        foreach ($feedItems as $feedItem) {
            $existing = $existingItems[$feedItem->getGuid()] ?? null;

            if ($existing !== null) {
                $existing->setTitle($feedItem->getTitle());
                $existing->setLink($feedItem->getLink());
                $existing->setSource($feedItem->getSource());
                $existing->setExcerpt($feedItem->getExcerpt());
            } else {
                $this->getEntityManager()->persist($feedItem);
            }
        }

        $this->getEntityManager()->flush();
    }

    public function getItemCountBySubscriptionGuid(
        string $subscriptionGuid,
    ): int {
        return $this->count(['subscriptionGuid' => $subscriptionGuid]);
    }

    /**
     * Trim feed items per subscription to a maximum limit.
     * Keeps the newest items and preserves bookmarked items.
     *
     * @return int Number of deleted items
     */
    public function trimToLimitPerSubscription(int $limit = 50): int
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        // Get all subscription guids that have items
        $subscriptionGuids = $conn->fetchFirstColumn(
            'SELECT DISTINCT subscription_guid FROM feed_item',
        );

        $totalDeleted = 0;

        foreach ($subscriptionGuids as $subscriptionGuid) {
            // Get GUIDs to keep (newest N items)
            $guidsToKeep = $conn->fetchFirstColumn(
                'SELECT guid FROM feed_item
                 WHERE subscription_guid = ?
                 ORDER BY published_at DESC
                 LIMIT ?',
                [$subscriptionGuid, $limit],
            );

            if (empty($guidsToKeep)) {
                continue;
            }

            // Delete items that are:
            // - From this subscription
            // - Not in the top N
            // - Not bookmarked
            $placeholders = implode(
                ',',
                array_fill(0, count($guidsToKeep), '?'),
            );
            $params = array_merge([$subscriptionGuid], $guidsToKeep);

            $deleted = $conn->executeStatement(
                "DELETE FROM feed_item
                 WHERE subscription_guid = ?
                 AND guid NOT IN ({$placeholders})
                 AND guid NOT IN (SELECT feed_item_guid FROM bookmark_status)",
                $params,
            );

            $totalDeleted += $deleted;
        }

        return $totalDeleted;
    }

    #[Returns('list<string>')]
    public function getGuidsBySubscriptionGuid(string $subscriptionGuid): array
    {
        $results = $this->createQueryBuilder('f')
            ->select('f.guid')
            ->where('f.subscriptionGuid = :subscriptionGuid')
            ->setParameter('subscriptionGuid', $subscriptionGuid)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'guid');
    }

    public function deleteBySubscriptionGuid(string $subscriptionGuid): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.subscriptionGuid = :subscriptionGuid')
            ->setParameter('subscriptionGuid', $subscriptionGuid)
            ->getQuery()
            ->execute();
    }

    #[Returns('list<array<string, mixed>>')]
    public function findItemsWithStatus(FeedItemQueryCriteria $criteria): array
    {
        if (empty($criteria->subscriptionGuids)) {
            return [];
        }

        $subscriptionGuids = $criteria->subscriptionGuids;
        $userId = $criteria->userId;
        $filterWords = $criteria->filterWords;
        $unreadOnly = $criteria->unreadOnly;
        $limit = $criteria->limit;
        $subscriptionGuid = $criteria->subscriptionGuid;
        $excludeFromUnreadFilter = $criteria->excludeFromUnreadFilter;
        $bookmarkedOnly = $criteria->bookmarkedOnly;

        // Use LEFT JOINs instead of EXISTS subqueries for better performance
        $qb = $this->createQueryBuilder('f')
            ->select(
                'f.guid',
                'f.subscriptionGuid as sguid',
                'f.title',
                'f.link',
                'f.source',
                'f.excerpt',
                'f.fetchedAt as fetchedAt',
                'f.publishedAt as publishedAt',
                'CASE WHEN rs.id IS NOT NULL THEN true ELSE false END as isRead',
                'CASE WHEN ss.id IS NOT NULL THEN true ELSE false END as isSeen',
                'CASE WHEN bm.id IS NOT NULL THEN true ELSE false END as isBookmarked',
            )
            ->leftJoin(
                ReadStatus::class,
                'rs',
                'WITH',
                'rs.feedItemGuid = f.guid AND rs.userId = :userId',
            )
            ->leftJoin(
                SeenStatus::class,
                'ss',
                'WITH',
                'ss.feedItemGuid = f.guid AND ss.userId = :userId',
            )
            ->leftJoin(
                BookmarkStatus::class,
                'bm',
                'WITH',
                'bm.feedItemGuid = f.guid AND bm.userId = :userId',
            )
            ->where('f.subscriptionGuid IN (:sguids)')
            ->setParameter('sguids', $subscriptionGuids)
            ->setParameter('userId', $userId)
            ->orderBy('f.publishedAt', 'DESC');

        // Filter by specific subscription
        if ($subscriptionGuid !== null) {
            $qb->andWhere('f.subscriptionGuid = :sguid')->setParameter(
                'sguid',
                $subscriptionGuid,
            );
        }

        // Add filter words conditions
        foreach ($filterWords as $i => $word) {
            $paramName = 'word'.$i;
            $qb->andWhere(
                "f.title NOT LIKE :{$paramName} AND f.excerpt NOT LIKE :{$paramName}",
            );
            $qb->setParameter($paramName, '%'.$word.'%');
        }

        // Filter unread only (with optional exclusion for active item)
        if ($unreadOnly) {
            if ($excludeFromUnreadFilter !== null) {
                $qb->andWhere(
                    '(rs.id IS NULL OR f.guid = :excludeGuid)',
                )->setParameter('excludeGuid', $excludeFromUnreadFilter);
            } else {
                $qb->andWhere('rs.id IS NULL');
            }
        }

        // Filter bookmarked only
        if ($bookmarkedOnly) {
            $qb->andWhere('bm.id IS NOT NULL');
        }

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'guid' => $row['guid'],
                'sguid' => $row['sguid'],
                'title' => $row['title'],
                'link' => $row['link'],
                'source' => $row['source'],
                'excerpt' => $row['excerpt'],
                'fetchedAt' => $row['fetchedAt'],
                'publishedAt' => $row['publishedAt'],
                'isRead' => (bool) $row['isRead'],
                'isNew' => !(bool) $row['isSeen'],
                'isBookmarked' => (bool) $row['isBookmarked'],
            ];
        }, $results);
    }

    #[Returns('list<string>')]
    public function getItemGuidsBySubscription(string $subscriptionGuid): array
    {
        $results = $this->createQueryBuilder('f')
            ->select('f.guid')
            ->where('f.subscriptionGuid = :sguid')
            ->setParameter('sguid', $subscriptionGuid)
            ->orderBy('f.publishedAt', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'guid');
    }

    #[Param(subscriptionGuids: 'list<string>')]
    #[Param(filterWords: 'list<string>')]
    #[Returns('array<string, int>')]
    public function getUnreadCountsBySubscription(
        array $subscriptionGuids,
        int $userId,
        array $filterWords = [],
    ): array {
        if (empty($subscriptionGuids)) {
            return [];
        }

        // Use LEFT JOIN instead of EXISTS subquery for better performance
        $qb = $this->createQueryBuilder('f')
            ->select(
                'f.subscriptionGuid as sguid',
                'COUNT(f.id) as unreadCount',
            )
            ->leftJoin(
                ReadStatus::class,
                'rs',
                'WITH',
                'rs.feedItemGuid = f.guid AND rs.userId = :userId',
            )
            ->where('f.subscriptionGuid IN (:sguids)')
            ->andWhere('rs.id IS NULL')
            ->setParameter('sguids', $subscriptionGuids)
            ->setParameter('userId', $userId);

        // Add filter words conditions
        foreach ($filterWords as $i => $word) {
            $paramName = 'word'.$i;
            $qb->andWhere(
                "f.title NOT LIKE :{$paramName} AND f.excerpt NOT LIKE :{$paramName}",
            );
            $qb->setParameter($paramName, '%'.$word.'%');
        }

        $results = $qb->groupBy('f.subscriptionGuid')->getQuery()->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['sguid']] = (int) $row['unreadCount'];
        }

        return $counts;
    }
}
