<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Entity;

use App\Entity\Subscription;
use App\Enum\SubscriptionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SubscriptionTest extends TestCase
{
    private function createSubscription(): Subscription
    {
        return new Subscription(
            userId: 1,
            url: 'https://example.com/feed.xml',
            name: 'Example Feed',
            guid: 'abc123def456789',
        );
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $subscription = $this->createSubscription();

        $this->assertEquals(1, $subscription->getUserId());
        $this->assertEquals(
            'https://example.com/feed.xml',
            $subscription->getUrl(),
        );
        $this->assertEquals('Example Feed', $subscription->getName());
        $this->assertEquals('abc123def456789', $subscription->getGuid());
    }

    #[Test]
    public function idIsNullBeforePersist(): void
    {
        $subscription = $this->createSubscription();

        $this->assertNull($subscription->getId());
    }

    #[Test]
    public function createdAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $subscription = $this->createSubscription();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $subscription->getCreatedAt());
        $this->assertLessThanOrEqual($after, $subscription->getCreatedAt());
    }

    #[Test]
    public function lastRefreshedAtIsNullInitially(): void
    {
        $subscription = $this->createSubscription();

        $this->assertNull($subscription->getLastRefreshedAt());
    }

    #[Test]
    public function setUrlUpdatesUrl(): void
    {
        $subscription = $this->createSubscription();

        $result = $subscription->setUrl('https://new.example.com/feed.xml');

        $this->assertEquals(
            'https://new.example.com/feed.xml',
            $subscription->getUrl(),
        );
        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function setNameUpdatesName(): void
    {
        $subscription = $this->createSubscription();

        $result = $subscription->setName('New Name');

        $this->assertEquals('New Name', $subscription->getName());
        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function updateLastRefreshedAtSetsTimestamp(): void
    {
        $subscription = $this->createSubscription();

        $before = new \DateTimeImmutable();
        $result = $subscription->updateLastRefreshedAt();
        $after = new \DateTimeImmutable();

        $this->assertNotNull($subscription->getLastRefreshedAt());
        $this->assertGreaterThanOrEqual(
            $before,
            $subscription->getLastRefreshedAt(),
        );
        $this->assertLessThanOrEqual(
            $after,
            $subscription->getLastRefreshedAt(),
        );
        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function folderIsNullInitially(): void
    {
        $subscription = $this->createSubscription();

        $this->assertNull($subscription->getFolder());
    }

    #[Test]
    public function setFolderUpdatesFolder(): void
    {
        $subscription = $this->createSubscription();

        $result = $subscription->setFolder('News');

        $this->assertEquals('News', $subscription->getFolder());
        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function setFolderAcceptsNull(): void
    {
        $subscription = $this->createSubscription();
        $subscription->setFolder('News');

        $result = $subscription->setFolder(null);

        $this->assertNull($subscription->getFolder());
        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function statusIsPendingInitially(): void
    {
        $subscription = $this->createSubscription();

        $this->assertEquals(
            SubscriptionStatus::Pending,
            $subscription->getStatus(),
        );
    }

    #[Test]
    public function setStatusUpdatesStatus(): void
    {
        $subscription = $this->createSubscription();

        $result = $subscription->setStatus(SubscriptionStatus::Success);

        $this->assertEquals(
            SubscriptionStatus::Success,
            $subscription->getStatus(),
        );
        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function setStatusAcceptsAllStatusValues(): void
    {
        $subscription = $this->createSubscription();

        foreach (SubscriptionStatus::cases() as $status) {
            $subscription->setStatus($status);
            $this->assertEquals($status, $subscription->getStatus());
        }
    }
}
