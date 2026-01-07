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

class FeedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedItem::class);
    }

    public function findByGuid(string $guid): ?FeedItem
    {
        return $this->findOneBy(["guid" => $guid]);
    }

    public function findByFeedGuid(string $feedGuid): array
    {
        return $this->findBy(
            ["feedGuid" => $feedGuid],
            ["publishedAt" => "DESC"],
        );
    }

    public function findAllOrderedByDate(): array
    {
        return $this->findBy([], ["publishedAt" => "DESC"]);
    }

    public function findByFeedGuids(array $feedGuids): array
    {
        if (empty($feedGuids)) {
            return [];
        }

        return $this->createQueryBuilder("f")
            ->where("f.feedGuid IN (:feedGuids)")
            ->setParameter("feedGuids", $feedGuids)
            ->orderBy("f.publishedAt", "DESC")
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
            $existing->updateFetchedAt();
        } else {
            $this->getEntityManager()->persist($feedItem);
        }

        $this->getEntityManager()->flush();
    }

    public function upsertBatch(array $feedItems): void
    {
        foreach ($feedItems as $feedItem) {
            $existing = $this->findByGuid($feedItem->getGuid());

            if ($existing !== null) {
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
        return $this->count(["feedGuid" => $feedGuid]);
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder("f")
            ->delete()
            ->where("f.publishedAt < :date")
            ->setParameter("date", $date)
            ->getQuery()
            ->execute();
    }

    public function getGuidsByFeedGuid(string $feedGuid): array
    {
        $results = $this->createQueryBuilder("f")
            ->select("f.guid")
            ->where("f.feedGuid = :feedGuid")
            ->setParameter("feedGuid", $feedGuid)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, "guid");
    }

    public function deleteByFeedGuid(string $feedGuid): int
    {
        return $this->createQueryBuilder("f")
            ->delete()
            ->where("f.feedGuid = :feedGuid")
            ->setParameter("feedGuid", $feedGuid)
            ->getQuery()
            ->execute();
    }
}
