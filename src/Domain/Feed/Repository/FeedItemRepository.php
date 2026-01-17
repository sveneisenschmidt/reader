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
            ->orderBy('f.fetchedAt', 'DESC')
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

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        $em = $this->getEntityManager();

        // Build subquery to exclude bookmarked items
        $bookmarkSubDql = $em
            ->createQueryBuilder()
            ->select('1')
            ->from(BookmarkStatus::class, 'bm')
            ->where('bm.feedItemGuid = f.guid')
            ->getDQL();

        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.publishedAt < :date')
            ->andWhere("NOT EXISTS({$bookmarkSubDql})")
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
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

        $em = $this->getEntityManager();

        // Build subqueries for read/seen/bookmarked status using DQL
        $readSubDql = $em
            ->createQueryBuilder()
            ->select('1')
            ->from(ReadStatus::class, 'rs')
            ->where('rs.feedItemGuid = f.guid')
            ->andWhere('rs.userId = :userId')
            ->getDQL();

        $seenSubDql = $em
            ->createQueryBuilder()
            ->select('1')
            ->from(SeenStatus::class, 'ss')
            ->where('ss.feedItemGuid = f.guid')
            ->andWhere('ss.userId = :userId')
            ->getDQL();

        $bookmarkSubDql = $em
            ->createQueryBuilder()
            ->select('1')
            ->from(BookmarkStatus::class, 'bm')
            ->where('bm.feedItemGuid = f.guid')
            ->andWhere('bm.userId = :userId')
            ->getDQL();

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
                "CASE WHEN EXISTS({$readSubDql}) THEN true ELSE false END as isRead",
                "CASE WHEN EXISTS({$seenSubDql}) THEN true ELSE false END as isSeen",
                "CASE WHEN EXISTS({$bookmarkSubDql}) THEN true ELSE false END as isBookmarked",
            )
            ->where('f.subscriptionGuid IN (:sguids)')
            ->setParameter('sguids', $subscriptionGuids)
            ->setParameter('userId', $userId)
            ->orderBy('f.fetchedAt', 'DESC');

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
            // Use separate alias for unread filter subquery to avoid conflict
            $unreadSubDql = $em
                ->createQueryBuilder()
                ->select('1')
                ->from(ReadStatus::class, 'rs2')
                ->where('rs2.feedItemGuid = f.guid')
                ->andWhere('rs2.userId = :userId')
                ->getDQL();

            if ($excludeFromUnreadFilter !== null) {
                $qb->andWhere(
                    "(NOT EXISTS({$unreadSubDql}) OR f.guid = :excludeGuid)",
                )->setParameter('excludeGuid', $excludeFromUnreadFilter);
            } else {
                $qb->andWhere("NOT EXISTS({$unreadSubDql})");
            }
        }

        // Filter bookmarked only
        if ($bookmarkedOnly) {
            $bookmarkFilterDql = $em
                ->createQueryBuilder()
                ->select('1')
                ->from(BookmarkStatus::class, 'bm2')
                ->where('bm2.feedItemGuid = f.guid')
                ->andWhere('bm2.userId = :userId')
                ->getDQL();

            $qb->andWhere("EXISTS({$bookmarkFilterDql})");
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
            ->orderBy('f.fetchedAt', 'DESC')
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

        $em = $this->getEntityManager();

        // Build subquery for read status using DQL
        $readSubDql = $em
            ->createQueryBuilder()
            ->select('1')
            ->from(ReadStatus::class, 'rs')
            ->where('rs.feedItemGuid = f.guid')
            ->andWhere('rs.userId = :userId')
            ->getDQL();

        $qb = $this->createQueryBuilder('f')
            ->select(
                'f.subscriptionGuid as sguid',
                'COUNT(f.id) as unreadCount',
            )
            ->where('f.subscriptionGuid IN (:sguids)')
            ->andWhere("NOT EXISTS({$readSubDql})")
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
