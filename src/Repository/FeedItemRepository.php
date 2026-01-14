<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository;

use App\Entity\FeedItem;
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
    public function findBySubscriptionGuid(string $subscriptionGuid): array
    {
        return $this->findBy(
            ['subscriptionGuid' => $subscriptionGuid],
            ['publishedAt' => 'DESC'],
        );
    }

    #[Returns('list<FeedItem>')]
    public function findAllOrderedByDate(): array
    {
        return $this->findBy([], ['publishedAt' => 'DESC']);
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

    public function getItemCountBySubscriptionGuid(
        string $subscriptionGuid,
    ): int {
        return $this->count(['subscriptionGuid' => $subscriptionGuid]);
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
}
