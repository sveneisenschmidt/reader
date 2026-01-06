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

class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findByUserId(int $userId): array
    {
        return $this->findBy(["userId" => $userId], ["createdAt" => "ASC"]);
    }

    public function findByUserIdAndGuid(
        int $userId,
        string $guid,
    ): ?Subscription {
        return $this->findOneBy([
            "userId" => $userId,
            "guid" => $guid,
        ]);
    }

    public function findByUserIdAndUrl(int $userId, string $url): ?Subscription
    {
        return $this->findOneBy([
            "userId" => $userId,
            "url" => $url,
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
}
