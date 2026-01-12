<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Service;

use App\Entity\Subscriptions\Subscription;
use App\Enum\SubscriptionStatus;
use App\Service\FeedExceptionHandler;
use FeedIo\Adapter\HttpRequestException;
use FeedIo\Adapter\NotFoundException;
use FeedIo\Adapter\ServerErrorException;
use FeedIo\Parser\MissingFieldsException;
use FeedIo\Parser\UnsupportedFormatException;
use FeedIo\Reader\NoAccurateParserException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class FeedExceptionHandlerTest extends TestCase
{
    private FeedExceptionHandler $handler;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new FeedExceptionHandler($this->logger);
    }

    private function createSubscription(): Subscription
    {
        return new Subscription(
            userId: 1,
            url: 'https://example.com/feed.xml',
            name: 'Test Feed',
            guid: 'test-guid',
        );
    }

    #[Test]
    public function handleExceptionLogsWarning(): void
    {
        $subscription = $this->createSubscription();
        $exception = new \Exception('Test error');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to refresh feed',
                $this->callback(function ($context) {
                    return $context['url'] === 'https://example.com/feed.xml'
                        && $context['error'] === 'Test error'
                        && isset($context['status']);
                }),
            );

        $this->handler->handleException($exception, $subscription);
    }

    #[Test]
    public function handleExceptionReturnsStatus(): void
    {
        $subscription = $this->createSubscription();
        $exception = new \Exception('Test error');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertInstanceOf(SubscriptionStatus::class, $status);
    }

    #[Test]
    public function httpRequestExceptionReturnsUnreachable(): void
    {
        $subscription = $this->createSubscription();
        $exception = new HttpRequestException('Connection failed');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Unreachable, $status);
    }

    #[Test]
    public function httpRequestExceptionWithTimeoutReturnsTimeout(): void
    {
        $subscription = $this->createSubscription();
        $exception = new HttpRequestException('Request timed out');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Timeout, $status);
    }

    #[Test]
    public function notFoundExceptionReturnsUnreachable(): void
    {
        $subscription = $this->createSubscription();
        $exception = new NotFoundException('Feed not found');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Unreachable, $status);
    }

    #[Test]
    public function notFoundExceptionWithTimeoutReturnsTimeout(): void
    {
        $subscription = $this->createSubscription();
        $exception = new NotFoundException('Operation timed out');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Timeout, $status);
    }

    #[Test]
    public function serverErrorExceptionReturnsUnreachable(): void
    {
        $subscription = $this->createSubscription();
        $response = $this->createStub(ResponseInterface::class);
        $exception = new ServerErrorException($response);

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Unreachable, $status);
    }

    #[Test]
    public function noAccurateParserExceptionReturnsInvalid(): void
    {
        $subscription = $this->createSubscription();
        $exception = new NoAccurateParserException('Cannot parse feed');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Invalid, $status);
    }

    #[Test]
    public function unsupportedFormatExceptionReturnsInvalid(): void
    {
        $subscription = $this->createSubscription();
        $exception = new UnsupportedFormatException('Unsupported format');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Invalid, $status);
    }

    #[Test]
    public function missingFieldsExceptionReturnsInvalid(): void
    {
        $subscription = $this->createSubscription();
        $exception = new MissingFieldsException('Missing required fields');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Invalid, $status);
    }

    #[Test]
    public function genericExceptionReturnsUnreachable(): void
    {
        $subscription = $this->createSubscription();
        $exception = new \Exception('Unknown error');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Unreachable, $status);
    }

    #[Test]
    public function runtimeExceptionReturnsUnreachable(): void
    {
        $subscription = $this->createSubscription();
        $exception = new \RuntimeException('Runtime error');

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Unreachable, $status);
    }

    #[Test]
    #[DataProvider('timeoutMessageProvider')]
    public function httpExceptionsWithVariousTimeoutMessagesReturnTimeout(
        string $message,
    ): void {
        $subscription = $this->createSubscription();
        $exception = new HttpRequestException($message);

        $status = $this->handler->handleException($exception, $subscription);

        $this->assertSame(SubscriptionStatus::Timeout, $status);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function timeoutMessageProvider(): array
    {
        return [
            'timed out' => ['Request timed out'],
            'timed out at end' => ['Connection timed out'],
            'timed out in middle' => [
                'The request timed out during connection',
            ],
        ];
    }
}
