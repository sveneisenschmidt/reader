<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Content;

use App\Entity\Content\FeedItem;
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

    #[Returns('list<FeedItem>')]
    public function findByFeedGuid(string $feedGuid): array
    {
        return $this->findBy(
            ['feedGuid' => $feedGuid],
            ['publishedAt' => 'DESC'],
        );
    }

    #[Returns('list<FeedItem>')]
    public function findAllOrderedByDate(): array
    {
        return $this->findBy([], ['publishedAt' => 'DESC']);
    }

    #[Param(feedGuids: 'list<string>')]
    #[Returns('list<FeedItem>')]
    public function findByFeedGuids(array $feedGuids): array
    {
        if (empty($feedGuids)) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->where('f.feedGuid IN (:feedGuids)')
            ->setParameter('feedGuids', $feedGuids)
            ->orderBy('f.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function upsert(FeedItem $feedItem): void
    {
        $existing = $this->findByGuid($feedItem->getGuid());

        if ($existing !== null) {
            // Skip if item belongs to a different subscription
            if ($existing->getFeedGuid() !== $feedItem->getFeedGuid()) {
                return;
            }

            $existing->setTitle($feedItem->getTitle());
            $existing->setLink($feedItem->getLink());
            $existing->setSource($feedItem->getSource());
            $existing->setExcerpt($feedItem->getExcerpt());
            $existing->updateFetchedAt();
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
                // Skip if item belongs to a different subscription
                if ($existing->getFeedGuid() !== $feedItem->getFeedGuid()) {
                    continue;
                }

                $existing->setTitle($feedItem->getTitle());
                $existing->setLink($feedItem->getLink());
                $existing->setSource($feedItem->getSource());
                $existing->setExcerpt($feedItem->getExcerpt());
                $existing->updateFetchedAt();
            } else {
                $this->getEntityManager()->persist($feedItem);
            }
        }

        $this->getEntityManager()->flush();
    }

    public function getItemCountByFeedGuid(string $feedGuid): int
    {
        return $this->count(['feedGuid' => $feedGuid]);
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.publishedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    #[Returns('list<string>')]
    public function getGuidsByFeedGuid(string $feedGuid): array
    {
        $results = $this->createQueryBuilder('f')
            ->select('f.guid')
            ->where('f.feedGuid = :feedGuid')
            ->setParameter('feedGuid', $feedGuid)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'guid');
    }

    public function deleteByFeedGuid(string $feedGuid): int
    {
        return $this->createQueryBuilder('f')
            ->delete()
            ->where('f.feedGuid = :feedGuid')
            ->setParameter('feedGuid', $feedGuid)
            ->getQuery()
            ->execute();
    }
}
