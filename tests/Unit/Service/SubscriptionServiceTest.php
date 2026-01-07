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
use PHPUnit\Framework\TestCase;

class SubscriptionServiceTest extends TestCase
{
    #[Test]
    public function getSubscriptionsForUserReturnsRepositoryResult(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                "guid1",
                "Feed 1",
                "https://example.com/feed1",
            ),
            $this->createSubscriptionStub(
                "guid2",
                "Feed 2",
                "https://example.com/feed2",
            ),
        ];

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method("findByUserId")
            ->with($userId)
            ->willReturn($subscriptions);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $result = $service->getSubscriptionsForUser($userId);

        $this->assertSame($subscriptions, $result);
    }

    #[Test]
    public function getSubscriptionsWithCountsCalculatesUnreadCounts(): void
    {
        $userId = 1;
        $sub1 = $this->createSubscriptionStub(
            "guid1",
            "Feed 1",
            "https://example.com/feed1",
        );
        $sub2 = $this->createSubscriptionStub(
            "guid2",
            "Feed 2",
            "https://example.com/feed2",
        );

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn([$sub1, $sub2]);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $items = [
            ["sguid" => "guid1", "isRead" => false],
            ["sguid" => "guid1", "isRead" => false],
            ["sguid" => "guid1", "isRead" => true],
            ["sguid" => "guid2", "isRead" => false],
        ];

        $result = $service->getSubscriptionsWithCounts($userId, $items);

        $this->assertCount(2, $result);
        $this->assertEquals(2, $result[0]["count"]); // guid1 has 2 unread
        $this->assertEquals(1, $result[1]["count"]); // guid2 has 1 unread
    }

    #[Test]
    public function getFeedUrlsReturnsMappedUrls(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                "guid1",
                "Feed 1",
                "https://example.com/feed1",
            ),
            $this->createSubscriptionStub(
                "guid2",
                "Feed 2",
                "https://example.com/feed2",
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn($subscriptions);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $result = $service->getFeedUrls($userId);

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
            $this->createSubscriptionStub(
                "guid1",
                "Feed 1",
                "https://example.com/feed1",
            ),
            $this->createSubscriptionStub(
                "guid2",
                "Feed 2",
                "https://example.com/feed2",
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn($subscriptions);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $result = $service->getFeedGuids($userId);

        $this->assertEquals(["guid1", "guid2"], $result);
    }

    #[Test]
    public function addSubscriptionCreatesNewSubscription(): void
    {
        $userId = 1;
        $url = "https://example.com/feed";
        $title = "Example Feed";
        $guid = "generated-guid";

        $feedFetcher = $this->createMock(FeedFetcher::class);
        $feedFetcher
            ->expects($this->once())
            ->method("getFeedTitle")
            ->with($url)
            ->willReturn($title);
        $feedFetcher
            ->expects($this->once())
            ->method("createGuid")
            ->with($url)
            ->willReturn($guid);

        $subscription = $this->createSubscriptionStub($guid, $title, $url);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method("addSubscription")
            ->with($userId, $url, $title, $guid)
            ->willReturn($subscription);

        $service = new SubscriptionService($repository, $feedFetcher);
        $result = $service->addSubscription($userId, $url);

        $this->assertSame($subscription, $result);
    }

    #[Test]
    public function removeSubscriptionDelegatesToRepository(): void
    {
        $userId = 1;
        $guid = "test-guid";

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository
            ->expects($this->once())
            ->method("removeSubscription")
            ->with($userId, $guid);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $service->removeSubscription($userId, $guid);
    }

    #[Test]
    public function enrichItemsWithSubscriptionNamesAddsSourceField(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                "guid1",
                "Feed One",
                "https://example.com/1",
            ),
            $this->createSubscriptionStub(
                "guid2",
                "Feed Two",
                "https://example.com/2",
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn($subscriptions);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $items = [
            ["sguid" => "guid1", "title" => "Item 1"],
            ["sguid" => "guid2", "title" => "Item 2"],
            ["sguid" => "unknown", "title" => "Item 3"],
        ];

        $result = $service->enrichItemsWithSubscriptionNames($items, $userId);

        $this->assertEquals("Feed One", $result[0]["source"]);
        $this->assertEquals("Feed Two", $result[1]["source"]);
        $this->assertArrayNotHasKey("source", $result[2]);
    }

    #[Test]
    public function toYamlFormatsSubscriptionsCorrectly(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                "guid1",
                "Feed One",
                "https://example.com/1",
            ),
            $this->createSubscriptionStub(
                "guid2",
                "Feed Two",
                "https://example.com/2",
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn($subscriptions);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $result = $service->toYaml($userId);

        $this->assertStringContainsString("https://example.com/1", $result);
        $this->assertStringContainsString("Feed One", $result);
        $this->assertStringContainsString("https://example.com/2", $result);
        $this->assertStringContainsString("Feed Two", $result);
    }

    #[Test]
    public function importFromYamlRejectsInvalidYamlSyntax(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid YAML syntax");

        $service->importFromYaml(1, "invalid: yaml: syntax: [");
    }

    #[Test]
    public function importFromYamlRejectsNonArrayData(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("YAML must be a list");

        $service->importFromYaml(1, "just_a_string");
    }

    #[Test]
    public function importFromYamlRejectsItemWithoutUrl(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Item 0 must have a 'url' string");

        $yaml = "- title: Missing URL\n";
        $service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsBlockedHost(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("blocked host");

        $yaml = "- url: http://localhost/feed\n  title: Local Feed\n";
        $service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsPrivateIpAddresses(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("blocked host");

        $yaml = "- url: http://192.168.1.1/feed\n";
        $service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsNonHttpSchemes(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must use http or https");

        $yaml = "- url: ftp://example.com/feed\n";
        $service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function getSubscriptionsWithCountsIncludesFolder(): void
    {
        $userId = 1;
        $sub1 = $this->createSubscriptionStub(
            "guid1",
            "Feed 1",
            "https://example.com/feed1",
            ["News", "Tech"],
        );
        $sub2 = $this->createSubscriptionStub(
            "guid2",
            "Feed 2",
            "https://example.com/feed2",
            null,
        );

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn([$sub1, $sub2]);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $result = $service->getSubscriptionsWithCounts($userId, []);

        $this->assertEquals(["News", "Tech"], $result[0]["folder"]);
        $this->assertNull($result[1]["folder"]);
    }

    #[Test]
    public function toYamlIncludesFolderWhenSet(): void
    {
        $userId = 1;
        $subscriptions = [
            $this->createSubscriptionStub(
                "guid1",
                "Feed One",
                "https://example.com/1",
                ["News", "Politics"],
            ),
            $this->createSubscriptionStub(
                "guid2",
                "Feed Two",
                "https://example.com/2",
                null,
            ),
        ];

        $repository = $this->createStub(SubscriptionRepository::class);
        $repository->method("findByUserId")->willReturn($subscriptions);

        $service = new SubscriptionService(
            $repository,
            $this->createStub(FeedFetcher::class),
        );

        $result = $service->toYaml($userId);

        $this->assertStringContainsString("folder:", $result);
        $this->assertStringContainsString("News", $result);
        $this->assertStringContainsString("Politics", $result);
    }

    #[Test]
    public function importFromYamlRejectsFolderAsNonArray(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Item 0 'folder' must be an array");

        $yaml = "- url: https://example.com/feed\n  folder: not-an-array\n";
        $service->importFromYaml(1, $yaml);
    }

    #[Test]
    public function importFromYamlRejectsFolderWithNonStringElements(): void
    {
        $service = new SubscriptionService(
            $this->createStub(SubscriptionRepository::class),
            $this->createStub(FeedFetcher::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Item 0 'folder' must contain only strings",
        );

        $yaml = "- url: https://example.com/feed\n  folder:\n    - 123\n";
        $service->importFromYaml(1, $yaml);
    }

    private function createSubscriptionStub(
        string $guid,
        string $name,
        string $url,
        ?array $folder = null,
    ): Subscription {
        $subscription = $this->createStub(Subscription::class);
        $subscription->method("getGuid")->willReturn($guid);
        $subscription->method("getName")->willReturn($name);
        $subscription->method("getUrl")->willReturn($url);
        $subscription->method("getFolder")->willReturn($folder);

        return $subscription;
    }
}
