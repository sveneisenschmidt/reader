<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Subscriptions;

use App\Repository\Subscriptions\SubscriptionRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubscriptionRepositoryTest extends KernelTestCase
{
    private SubscriptionRepository $repository;
    private int $testUserId = 99999;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(
            SubscriptionRepository::class,
        );
    }

    protected function tearDown(): void
    {
        // Clean up test subscriptions
        $subscriptions = $this->repository->findByUserId($this->testUserId);
        foreach ($subscriptions as $subscription) {
            $this->repository->removeSubscription(
                $this->testUserId,
                $subscription->getGuid(),
            );
        }
        parent::tearDown();
    }

    #[Test]
    public function findByUserIdReturnsEmptyArrayWhenNoSubscriptions(): void
    {
        $result = $this->repository->findByUserId(88888);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findByUserIdReturnsSubscriptions(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/feed1.xml",
            "Test Feed 1",
            "subrepotest12345",
        );

        $result = $this->repository->findByUserId($this->testUserId);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function findByUserIdAndGuidReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByUserIdAndGuid(
            $this->testUserId,
            "nonexistent12345",
        );

        $this->assertNull($result);
    }

    #[Test]
    public function findByUserIdAndGuidReturnsSubscription(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/feed2.xml",
            "Test Feed 2",
            "subrepotest23456",
        );

        $result = $this->repository->findByUserIdAndGuid(
            $this->testUserId,
            "subrepotest23456",
        );

        $this->assertNotNull($result);
        $this->assertEquals("subrepotest23456", $result->getGuid());
    }

    #[Test]
    public function findByUserIdAndUrlReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByUserIdAndUrl(
            $this->testUserId,
            "https://nonexistent.com/feed.xml",
        );

        $this->assertNull($result);
    }

    #[Test]
    public function findByUserIdAndUrlReturnsSubscription(): void
    {
        $url = "https://example.com/urltest.xml";
        $this->repository->addSubscription(
            $this->testUserId,
            $url,
            "URL Test Feed",
            "subrepotest34567",
        );

        $result = $this->repository->findByUserIdAndUrl(
            $this->testUserId,
            $url,
        );

        $this->assertNotNull($result);
        $this->assertEquals($url, $result->getUrl());
    }

    #[Test]
    public function addSubscriptionCreatesNewSubscription(): void
    {
        $subscription = $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/new.xml",
            "New Feed",
            "subrepotest45678",
        );

        $this->assertNotNull($subscription);
        $this->assertEquals("New Feed", $subscription->getName());
        $this->assertEquals($this->testUserId, $subscription->getUserId());
    }

    #[Test]
    public function addSubscriptionReturnsExistingForDuplicateUrl(): void
    {
        $url = "https://example.com/duplicate.xml";

        $first = $this->repository->addSubscription(
            $this->testUserId,
            $url,
            "First Name",
            "subrepotest56789",
        );

        $second = $this->repository->addSubscription(
            $this->testUserId,
            $url,
            "Second Name",
            "subrepotest67890",
        );

        $this->assertEquals($first->getId(), $second->getId());
        $this->assertEquals("First Name", $second->getName());
    }

    #[Test]
    public function removeSubscriptionDeletesSubscription(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/remove.xml",
            "Remove Feed",
            "subrepotest78901",
        );

        $this->repository->removeSubscription(
            $this->testUserId,
            "subrepotest78901",
        );

        $result = $this->repository->findByUserIdAndGuid(
            $this->testUserId,
            "subrepotest78901",
        );
        $this->assertNull($result);
    }

    #[Test]
    public function removeSubscriptionDoesNothingForNonexistent(): void
    {
        // Should not throw
        $this->repository->removeSubscription(
            $this->testUserId,
            "nonexistent99999",
        );

        $this->assertTrue(true);
    }

    #[Test]
    public function findByGuidIsAliasForFindByUserIdAndGuid(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/alias.xml",
            "Alias Feed",
            "subrepotest89012",
        );

        $result = $this->repository->findByGuid(
            $this->testUserId,
            "subrepotest89012",
        );

        $this->assertNotNull($result);
        $this->assertEquals("subrepotest89012", $result->getGuid());
    }

    #[Test]
    public function updateNameChangesSubscriptionName(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/rename.xml",
            "Original Name",
            "subrepotest90123",
        );

        $this->repository->updateName(
            $this->testUserId,
            "subrepotest90123",
            "Updated Name",
        );

        $result = $this->repository->findByUserIdAndGuid(
            $this->testUserId,
            "subrepotest90123",
        );
        $this->assertEquals("Updated Name", $result->getName());
    }

    #[Test]
    public function updateNameDoesNothingForNonexistent(): void
    {
        // Should not throw
        $this->repository->updateName(
            $this->testUserId,
            "nonexistent11111",
            "New Name",
        );

        $this->assertTrue(true);
    }

    #[Test]
    public function updateFolderChangesSubscriptionFolder(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/folder.xml",
            "Folder Feed",
            "subrepotest01234",
        );

        $this->repository->updateFolder(
            $this->testUserId,
            "subrepotest01234",
            "tech",
        );

        $result = $this->repository->findByUserIdAndGuid(
            $this->testUserId,
            "subrepotest01234",
        );
        $this->assertEquals("tech", $result->getFolder());
    }

    #[Test]
    public function updateFolderDoesNothingForNonexistent(): void
    {
        // Should not throw when subscription doesn't exist
        $this->repository->updateFolder(
            $this->testUserId,
            "nonexistent22222",
            "testfolder",
        );

        $this->assertTrue(true);
    }

    #[Test]
    public function updateAllRefreshTimestampsUpdatesAllSubscriptions(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/refresh1.xml",
            "Refresh Feed 1",
            "subreporefresh01",
        );
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/refresh2.xml",
            "Refresh Feed 2",
            "subreporefresh02",
        );

        $this->repository->updateAllRefreshTimestamps($this->testUserId);

        $subscriptions = $this->repository->findByUserId($this->testUserId);
        foreach ($subscriptions as $subscription) {
            $this->assertNotNull($subscription->getLastRefreshedAt());
        }
    }

    #[Test]
    public function hasAnyForUserReturnsFalseWhenNoSubscriptions(): void
    {
        $result = $this->repository->hasAnyForUser(77777);

        $this->assertFalse($result);
    }

    #[Test]
    public function hasAnyForUserReturnsTrueWhenHasSubscriptions(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/hasany.xml",
            "Has Any Feed",
            "subrepohasany001",
        );

        $result = $this->repository->hasAnyForUser($this->testUserId);

        $this->assertTrue($result);
    }

    #[Test]
    public function countByUserIdReturnsZeroForNoSubscriptions(): void
    {
        $count = $this->repository->countByUserId(66666);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function countByUserIdReturnsCorrectCount(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/count1.xml",
            "Count Feed 1",
            "subrepocounttest1",
        );
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/count2.xml",
            "Count Feed 2",
            "subrepocounttest2",
        );

        $count = $this->repository->countByUserId($this->testUserId);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    #[Test]
    public function getOldestRefreshTimeReturnsNullWhenNoRefreshed(): void
    {
        // User with no subscriptions
        $result = $this->repository->getOldestRefreshTime(55555);

        $this->assertNull($result);
    }

    #[Test]
    public function getOldestRefreshTimeReturnsOldestTime(): void
    {
        $this->repository->addSubscription(
            $this->testUserId,
            "https://example.com/oldest.xml",
            "Oldest Feed",
            "subrepooldest001",
        );

        $this->repository->updateAllRefreshTimestamps($this->testUserId);

        $result = $this->repository->getOldestRefreshTime($this->testUserId);

        $this->assertInstanceOf(\DateTimeImmutable::class, $result);
    }
}
