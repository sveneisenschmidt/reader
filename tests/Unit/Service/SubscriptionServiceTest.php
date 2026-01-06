<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Entity\Subscriptions\Subscription;
use App\Repository\Subscriptions\SubscriptionRepository;
use App\Service\FeedFetcher;
use App\Service\SubscriptionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriptionServiceTest extends TestCase
{
    private SubscriptionRepository&MockObject $subscriptionRepository;
    private FeedFetcher&MockObject $feedFetcher;
    private SubscriptionService $service;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(
            SubscriptionRepository::class,
        );
        $this->feedFetcher = $this->createMock(FeedFetcher::class);

        $this->service = new SubscriptionService(
            $this->subscriptionRepository,
            $this->feedFetcher,
        );
    }

    #[Test]
    public function getSubscriptionsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscription(
                "guid1",
                "Feed 1",
                "https://example.com/feed1",
            ),
            $this->createSubscription(
                "guid2",
                "Feed 2",
                "https://example.com/feed2",
            ),
        ];

        $this->subscriptionRepository
            ->expects($this->once())
            ->method("findByUserId")
            ->with($userId)
            ->willReturn($subscriptions);

        $result = $this->service->getSubscriptionsForUser($userId);

        $this->assertSame($subscriptions, $result);
    }

    #[Test]
    public function getSubscriptionsWithCountsCalculatesUnreadCounts(): void
    {
        $userId = 1;
        $sub1 = $this->createSubscription(
            "guid1",
            "Feed 1",
            "https://example.com/feed1",
        );
        $sub2 = $this->createSubscription(
            "guid2",
            "Feed 2",
            "https://example.com/feed2",
        );

        $this->subscriptionRepository
            ->method("findByUserId")
            ->willReturn([$sub1, $sub2]);

        $items = [
            ["sguid" => "guid1", "isRead" => false],
            ["sguid" => "guid1", "isRead" => false],
            ["sguid" => "guid1", "isRead" => true],
            ["sguid" => "guid2", "isRead" => false],
        ];

        $result = $this->service->getSubscriptionsWithCounts($userId, $items);

        $this->assertCount(2, $result);
        $this->assertEquals(2, $result[0]["count"]); // guid1 has 2 unread
        $this->assertEquals(1, $result[1]["count"]); // guid2 has 1 unread
    }

    #[Test]
    public function getFeedUrlsReturnsMappedUrls(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscription(
                "guid1",
                "Feed 1",
                "https://example.com/feed1",
            ),
            $this->createSubscription(
                "guid2",
                "Feed 2",
                "https://example.com/feed2",
            ),
        ];

        $this->subscriptionRepository
            ->method("findByUserId")
            ->willReturn($subscriptions);

        $result = $this->service->getFeedUrls($userId);

        $this->assertEquals(
            ["https://example.com/feed1", "https://example.com/feed2"],
            $result,
        );
    }

    #[Test]
    public function getFeedGuidsReturnsMappedGuids(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscription(
                "guid1",
                "Feed 1",
                "https://example.com/feed1",
            ),
            $this->createSubscription(
                "guid2",
                "Feed 2",
                "https://example.com/feed2",
            ),
        ];

        $this->subscriptionRepository
            ->method("findByUserId")
            ->willReturn($subscriptions);

        $result = $this->service->getFeedGuids($userId);

        $this->assertEquals(["guid1", "guid2"], $result);
    }

    #[Test]
    public function addSubscriptionCreatesNewSubscription(): void
    {
        $userId = 1;
        $url = "https://example.com/feed";
        $title = "Example Feed";
        $guid = "generated-guid";

        $this->feedFetcher
            ->expects($this->once())
            ->method("getFeedTitle")
            ->with($url)
            ->willReturn($title);

        $this->feedFetcher
            ->expects($this->once())
            ->method("createGuid")
            ->with($url)
            ->willReturn($guid);

        $subscription = $this->createSubscription($guid, $title, $url);

        $this->subscriptionRepository
            ->expects($this->once())
            ->method("addSubscription")
            ->with($userId, $url, $title, $guid)
            ->willReturn($subscription);

        $result = $this->service->addSubscription($userId, $url);

        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function removeSubscriptionDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = "test-guid";

        $this->subscriptionRepository
            ->expects($this->once())
            ->method("removeSubscription")
            ->with($userId, $guid);

        $this->service->removeSubscription($userId, $guid);
    }

    #[Test]
    public function enrichItemsWithSubscriptionNamesAddsSourceField(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscription(
                "guid1",
                "Feed One",
                "https://example.com/1",
            ),
            $this->createSubscription(
                "guid2",
                "Feed Two",
                "https://example.com/2",
            ),
        ];

        $this->subscriptionRepository
            ->method("findByUserId")
            ->willReturn($subscriptions);

        $items = [
            ["sguid" => "guid1", "title" => "Item 1"],
            ["sguid" => "guid2", "title" => "Item 2"],
            ["sguid" => "unknown", "title" => "Item 3"],
        ];

        $result = $this->service->enrichItemsWithSubscriptionNames(
            $items,
            $userId,
        );

        $this->assertEquals("Feed One", $result[0]["source"]);
        $this->assertEquals("Feed Two", $result[1]["source"]);
        $this->assertArrayNotHasKey("source", $result[2]);
    }

    #[Test]
    public function toYamlFormatsSubscriptionsCorrectly(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscription(
                "guid1",
                "Feed One",
                "https://example.com/1",
            ),
            $this->createSubscription(
                "guid2",
                "Feed Two",
                "https://example.com/2",
            ),
        ];

        $this->subscriptionRepository
            ->method("findByUserId")
            ->willReturn($subscriptions);

        $result = $this->service->toYaml($userId);

        // YAML may quote URLs, so check for the essential parts
        $this->assertStringContainsString("https://example.com/1", $result);
        $this->assertStringContainsString("Feed One", $result);
        $this->assertStringContainsString("https://example.com/2", $result);
        $this->assertStringContainsString("Feed Two", $result);
    }

    #[Test]
    public function importFromYamlRejectsInvalidYamlSyntax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid YAML syntax");

        $this->service->importFromYaml(1, "invalid: yaml: syntax: [");
    }

    #[Test]
    public function importFromYamlRejectsNonArrayData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("YAML must be a list");

        $this->service->importFromYaml(1, "just_a_string");
    }

    #[Test]
    public function importFromYamlRejectsItemWithoutUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Item 0 must have a 'url' string");

        $yaml = "- title: Missing URL\n";

        $this->service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsBlockedHost(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("blocked host");

        $yaml = "- url: http://localhost/feed\n  title: Local Feed\n";

        $this->service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsPrivateIpAddresses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("blocked host");

        $yaml = "- url: http://192.168.1.1/feed\n";

        $this->service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsNonHttpSchemes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must use http or https");

        // Use ftp:// which has a proper host to pass URL parsing
        $yaml = "- url: ftp://example.com/feed\n";

        $this->service->importFromYaml(1, $yaml);
    }

    private function createSubscription(
        string $guid,
        string $name,
        string $url,
    ): Subscription&MockObject {
        $subscription = $this->createMock(Subscription::class);
        $subscription->method("getGuid")->willReturn($guid);
        $subscription->method("getName")->willReturn($name);
        $subscription->method("getUrl")->willReturn($url);

        return $subscription;
    }
}
