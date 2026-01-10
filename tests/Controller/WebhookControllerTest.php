<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Entity\Messages\ProcessedMessage;
use App\Message\CleanupContentMessage;
use App\Message\RefreshFeedsMessage;
use App\Repository\Messages\ProcessedMessageRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class WebhookControllerTest extends WebTestCase
{
    private const WEBHOOK_USER = 'webhook_test';
    private const WEBHOOK_PASSWORD = 'webhook_secret';

    #[Test]
    public function refreshFeedsRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/webhook/refresh-feeds');

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function refreshFeedsRejectsInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/webhook/refresh-feeds',
            [],
            [],
            [
                'PHP_AUTH_USER' => 'invalid',
                'PHP_AUTH_PW' => 'invalid',
            ],
        );

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function refreshFeedsAcceptsValidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/webhook/refresh-feeds',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    #[Test]
    public function refreshFeedsRequiresGetMethod(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/webhook/refresh-feeds',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function cleanupContentRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/webhook/cleanup-content');

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function cleanupContentRejectsInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/webhook/cleanup-content',
            [],
            [],
            [
                'PHP_AUTH_USER' => 'invalid',
                'PHP_AUTH_PW' => 'invalid',
            ],
        );

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function cleanupContentAcceptsValidCredentials(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/webhook/cleanup-content',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    #[Test]
    public function cleanupContentRequiresGetMethod(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/webhook/cleanup-content',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseStatusCodeSame(405);
    }

    #[Test]
    public function refreshFeedsLogsExecution(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/webhook/refresh-feeds',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();

        $repository = static::getContainer()->get(
            ProcessedMessageRepository::class,
        );
        $lastEntry = $repository->getLastSuccessByType(
            RefreshFeedsMessage::class,
        );

        $this->assertNotNull($lastEntry);
        $this->assertEquals(
            ProcessedMessage::STATUS_SUCCESS,
            $lastEntry->getStatus(),
        );
    }

    #[Test]
    public function cleanupContentLogsExecution(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/webhook/cleanup-content',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseIsSuccessful();

        $repository = static::getContainer()->get(
            ProcessedMessageRepository::class,
        );
        $lastEntry = $repository->getLastSuccessByType(
            CleanupContentMessage::class,
        );

        $this->assertNotNull($lastEntry);
        $this->assertEquals(
            ProcessedMessage::STATUS_SUCCESS,
            $lastEntry->getStatus(),
        );
    }

    #[Test]
    public function refreshFeedsReturnsErrorOnException(): void
    {
        $client = static::createClient();

        $mockBus = $this->createMock(MessageBusInterface::class);
        $mockBus
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Test error'));

        static::getContainer()->set(MessageBusInterface::class, $mockBus);

        $client->request(
            'GET',
            '/webhook/refresh-feeds',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseStatusCodeSame(500);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $response['status']);
        $this->assertEquals('Test error', $response['message']);
    }

    #[Test]
    public function cleanupContentReturnsErrorOnException(): void
    {
        $client = static::createClient();

        $mockBus = $this->createMock(MessageBusInterface::class);
        $mockBus
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Cleanup failed'));

        static::getContainer()->set(MessageBusInterface::class, $mockBus);

        $client->request(
            'GET',
            '/webhook/cleanup-content',
            [],
            [],
            [
                'PHP_AUTH_USER' => self::WEBHOOK_USER,
                'PHP_AUTH_PW' => self::WEBHOOK_PASSWORD,
            ],
        );

        $this->assertResponseStatusCodeSame(500);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('error', $response['status']);
        $this->assertEquals('Cleanup failed', $response['message']);
    }
}
