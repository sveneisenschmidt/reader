<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Entity\Logs\LogEntry;
use App\Repository\Logs\LogEntryRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class WebhookControllerTest extends WebTestCase
{
    private const WEBHOOK_USER = "webhook_test";
    private const WEBHOOK_PASSWORD = "webhook_secret";

    #[Test]
    public function refreshFeedsRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request("GET", "/webhook/refresh-feeds");

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function refreshFeedsRejectsInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/webhook/refresh-feeds",
            [],
            [],
            [
                "PHP_AUTH_USER" => "invalid",
                "PHP_AUTH_PW" => "invalid",
            ],
        );

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function refreshFeedsAcceptsValidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/webhook/refresh-feeds",
            [],
            [],
            [
                "PHP_AUTH_USER" => self::WEBHOOK_USER,
                "PHP_AUTH_PW" => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals("ok", $response["status"]);
    }

    #[Test]
    public function refreshFeedsRequiresGetMethod(): void
    {
        $client = static::createClient();

        $client->request(
            "POST",
            "/webhook/refresh-feeds",
            [],
            [],
            [
                "PHP_AUTH_USER" => self::WEBHOOK_USER,
                "PHP_AUTH_PW" => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function cleanupContentRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request("GET", "/webhook/cleanup-content");

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function cleanupContentRejectsInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/webhook/cleanup-content",
            [],
            [],
            [
                "PHP_AUTH_USER" => "invalid",
                "PHP_AUTH_PW" => "invalid",
            ],
        );

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function cleanupContentAcceptsValidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/webhook/cleanup-content",
            [],
            [],
            [
                "PHP_AUTH_USER" => self::WEBHOOK_USER,
                "PHP_AUTH_PW" => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals("ok", $response["status"]);
    }

    #[Test]
    public function cleanupContentRequiresGetMethod(): void
    {
        $client = static::createClient();

        $client->request(
            "POST",
            "/webhook/cleanup-content",
            [],
            [],
            [
                "PHP_AUTH_USER" => self::WEBHOOK_USER,
                "PHP_AUTH_PW" => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function refreshFeedsLogsExecution(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/webhook/refresh-feeds",
            [],
            [],
            [
                "PHP_AUTH_USER" => self::WEBHOOK_USER,
                "PHP_AUTH_PW" => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();

        $logEntryRepository = static::getContainer()->get(
            LogEntryRepository::class,
        );
        $lastEntry = $logEntryRepository->getLastByChannelAndAction(
            LogEntry::CHANNEL_WEBHOOK,
            "refresh-feeds",
        );

        $this->assertNotNull($lastEntry);
        $this->assertEquals(LogEntry::STATUS_SUCCESS, $lastEntry->getStatus());
    }

    #[Test]
    public function cleanupContentLogsExecution(): void
    {
        $client = static::createClient();

        $client->request(
            "GET",
            "/webhook/cleanup-content",
            [],
            [],
            [
                "PHP_AUTH_USER" => self::WEBHOOK_USER,
                "PHP_AUTH_PW" => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();

        $logEntryRepository = static::getContainer()->get(
            LogEntryRepository::class,
        );
        $lastEntry = $logEntryRepository->getLastByChannelAndAction(
            LogEntry::CHANNEL_WEBHOOK,
            "cleanup-content",
        );

        $this->assertNotNull($lastEntry);
        $this->assertEquals(LogEntry::STATUS_SUCCESS, $lastEntry->getStatus());
    }
}
