<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Repository\Subscriptions;

use App\Entity\Subscriptions\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Template;

#[Template('T', Subscription::class)]
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    #[Returns('list<Subscription>')]
    public function findByUserId(int $userId): array
    {
        return $this->findBy(['userId' => $userId], ['createdAt' => 'ASC']);
    }

    public function findByUserIdAndGuid(
        int $userId,
        string $guid,
    ): ?Subscription {
        return $this->findOneBy([
            'userId' => $userId,
            'guid' => $guid,
        ]);
    }

    public function findByUserIdAndUrl(int $userId, string $url): ?Subscription
    {
        return $this->findOneBy([
            'userId' => $userId,
            'url' => $url,
        ]);
    }

    public function addSubscription(
        int $userId,
        string $url,
        string $name,
        string $guid,
    ): Subscription {
        $existing = $this->findByUserIdAndUrl($userId, $url);

        if ($existing !== null) {
            return $existing;
        }

        $subscription = new Subscription($userId, $url, $name, $guid);
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();

        return $subscription;
    }

    public function removeSubscription(int $userId, string $guid): void
    {
        $subscription = $this->findByUserIdAndGuid($userId, $guid);

        if ($subscription !== null) {
            $this->getEntityManager()->remove($subscription);
            $this->getEntityManager()->flush();
        }
    }

    public function findByGuid(int $userId, string $guid): ?Subscription
    {
        return $this->findByUserIdAndGuid($userId, $guid);
    }

    public function updateName(int $userId, string $guid, string $name): void
    {
        $subscription = $this->findByUserIdAndGuid($userId, $guid);

        if ($subscription !== null) {
            $subscription->setName($name);
            $this->getEntityManager()->flush();
        }
    }

    public function updateFolder(
        int $userId,
        string $guid,
        ?string $folder,
    ): void {
        $subscription = $this->findByUserIdAndGuid($userId, $guid);

        if ($subscription !== null) {
            $subscription->setFolder($folder);
            $this->getEntityManager()->flush();
        }
    }

    public function hasAnyForUser(int $userId): bool
    {
        return $this->count(['userId' => $userId]) > 0;
    }

    public function countByUserId(int $userId): int
    {
        return $this->count(['userId' => $userId]);
    }

    public function getOldestRefreshTime(int $userId): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('s')
            ->select('MIN(s.lastRefreshedAt)')
            ->where('s.userId = :userId')
            ->andWhere('s.lastRefreshedAt IS NOT NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        if ($result === null) {
            return null;
        }

        return new \DateTimeImmutable($result);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function save(Subscription $subscription): void
    {
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }
}
