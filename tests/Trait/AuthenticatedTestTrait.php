<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Trait;

use App\Entity\FeedItem;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\FeedItemRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
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

        $user = $userRepository->findByUsername('test@example.com');
        if (!$user) {
            $user = new User('test@example.com');
            $user->setEmail('test@example.com');
            $user->setPassword(
                $passwordHasher->hashPassword($user, 'testpassword'),
            );
            $user->setTotpSecret('JBSWY3DPEHPK3PXP');
            $userRepository->save($user);
        }

        $this->testUser = $user;
        $client->loginUser($user);

        return $user;
    }

    private function createTestSubscription(
        string $guid = '0123456789abcdef',
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
                $withRefreshTimestamp
                && $existing->getLastRefreshedAt() === null
            ) {
                $existing->updateLastRefreshedAt();
                $subscriptionRepository->getEntityManager()->flush();
            }

            return $existing;
        }

        $subscription = $subscriptionRepository->addSubscription(
            $this->testUser->getId(),
            'https://example.com/feed.xml',
            'Test Feed',
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
        string $subscriptionGuid = '0123456789abcdef',
        string $itemGuid = 'fedcba9876543210',
    ): FeedItem {
        $container = static::getContainer();
        $feedItemRepository = $container->get(FeedItemRepository::class);

        $existing = $feedItemRepository->findByGuid($itemGuid);
        if ($existing) {
            return $existing;
        }

        $feedItem = new FeedItem(
            $itemGuid,
            $subscriptionGuid,
            'Test Feed Item',
            'https://example.com/item',
            'Test Feed',
            '<p>This is a test feed item excerpt.</p>',
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

    private function createTestFeedItemWithLink(
        string $subscriptionGuid = '0123456789abcdef',
        string $itemGuid = 'fedcba9876543210',
    ): FeedItem {
        $container = static::getContainer();
        $feedItemRepository = $container->get(FeedItemRepository::class);

        $existing = $feedItemRepository->findByGuid($itemGuid);
        if ($existing) {
            $feedItemRepository->getEntityManager()->remove($existing);
            $feedItemRepository->getEntityManager()->flush();
        }

        $feedItem = new FeedItem(
            $itemGuid,
            $subscriptionGuid,
            'Test Feed Item With Link',
            'https://example.com/article',
            'Test Feed',
            '<p>Content with <a href="https://linked-site.com/page">a link</a>.</p>',
            new \DateTimeImmutable(),
        );

        $feedItemRepository->upsert($feedItem);

        return $feedItem;
    }

    private function ensureTestUserHasSubscriptionWithItemContainingLink(
        KernelBrowser $client,
    ): void {
        $this->loginAsTestUser($client);
        $subscription = $this->createTestSubscription();
        $this->createTestFeedItemWithLink($subscription->getGuid());
    }

    private function createTestFeedItemWithEmptyContent(
        string $subscriptionGuid = '0123456789abcdef',
        string $itemGuid = 'fedcba9876543210',
    ): FeedItem {
        $container = static::getContainer();
        $feedItemRepository = $container->get(FeedItemRepository::class);

        $existing = $feedItemRepository->findByGuid($itemGuid);
        if ($existing) {
            $feedItemRepository->getEntityManager()->remove($existing);
            $feedItemRepository->getEntityManager()->flush();
        }

        $feedItem = new FeedItem(
            $itemGuid,
            $subscriptionGuid,
            'Test Feed Item With Empty Content',
            'https://example.com/article',
            'Test Feed',
            '',
            new \DateTimeImmutable(),
        );

        $feedItemRepository->upsert($feedItem);

        return $feedItem;
    }

    private function ensureTestUserHasSubscriptionWithItemEmptyContent(
        KernelBrowser $client,
    ): void {
        $this->loginAsTestUser($client);
        $subscription = $this->createTestSubscription();
        $this->createTestFeedItemWithEmptyContent($subscription->getGuid());
    }

    private function ensureTestUserHasSubscriptionWithTwoItems(
        KernelBrowser $client,
    ): void {
        $this->loginAsTestUser($client);
        $subscription = $this->createTestSubscription();
        $this->createTestFeedItem($subscription->getGuid(), 'aaaaaaaaaaaaaaa1');
        $this->createTestFeedItem($subscription->getGuid(), 'aaaaaaaaaaaaaaa2');
    }
}
