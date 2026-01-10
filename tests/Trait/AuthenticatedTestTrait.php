<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Trait;

use App\Entity\Content\FeedItem;
use App\Entity\Subscriptions\Subscription;
use App\Entity\Users\User;
use App\Repository\Content\FeedItemRepository;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Repository\Users\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait AuthenticatedTestTrait
{
    private ?User $testUser = null;

    private function loginAsTestUser(KernelBrowser $client): User
    {
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = $userRepository->findByUsername("test@example.com");
        if (!$user) {
            $user = new User("test@example.com");
            $user->setEmail("test@example.com");
            $user->setPassword(
                $passwordHasher->hashPassword($user, "testpassword"),
            );
            $user->setTotpSecret("JBSWY3DPEHPK3PXP");
            $userRepository->save($user);
        }

        $this->testUser = $user;
        $client->loginUser($user);

        return $user;
    }

    private function createTestSubscription(
        string $guid = "0123456789abcdef",
        bool $withRefreshTimestamp = true,
    ): Subscription {
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );

        $existing = $subscriptionRepository->findByUserIdAndGuid(
            $this->testUser->getId(),
            $guid,
        );

        if ($existing) {
            if (
                $withRefreshTimestamp &&
                $existing->getLastRefreshedAt() === null
            ) {
                $existing->updateLastRefreshedAt();
                $subscriptionRepository->getEntityManager()->flush();
            }
            return $existing;
        }

        $subscription = $subscriptionRepository->addSubscription(
            $this->testUser->getId(),
            "https://example.com/feed.xml",
            "Test Feed",
            $guid,
        );

        if ($withRefreshTimestamp) {
            $subscription->updateLastRefreshedAt();
            $subscriptionRepository->getEntityManager()->flush();
        }

        return $subscription;
    }

    private function deleteAllSubscriptionsForTestUser(): void
    {
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );

        $subscriptions = $subscriptionRepository->findByUserId(
            $this->testUser->getId(),
        );

        foreach ($subscriptions as $subscription) {
            $subscriptionRepository->removeSubscription(
                $this->testUser->getId(),
                $subscription->getGuid(),
            );
        }
    }

    private function ensureTestUserHasSubscription(KernelBrowser $client): void
    {
        $this->loginAsTestUser($client);
        $this->createTestSubscription();
    }

    private function createTestFeedItem(
        string $feedGuid = "0123456789abcdef",
        string $itemGuid = "fedcba9876543210",
    ): FeedItem {
        $container = static::getContainer();
        $feedItemRepository = $container->get(FeedItemRepository::class);

        $existing = $feedItemRepository->findByGuid($itemGuid);
        if ($existing) {
            return $existing;
        }

        $feedItem = new FeedItem(
            $itemGuid,
            $feedGuid,
            "Test Feed Item",
            "https://example.com/item",
            "Test Feed",
            "<p>This is a test feed item excerpt.</p>",
            new \DateTimeImmutable(),
        );

        $feedItemRepository->upsert($feedItem);

        return $feedItem;
    }

    private function ensureTestUserHasSubscriptionWithItem(
        KernelBrowser $client,
    ): void {
        $this->loginAsTestUser($client);
        $subscription = $this->createTestSubscription();
        $this->createTestFeedItem($subscription->getGuid());
    }
}
