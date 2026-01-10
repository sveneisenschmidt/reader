<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Messenger\Middleware;

use App\Entity\Messages\ProcessedMessage;
use App\Enum\MessageSource;
use App\Message\HeartbeatMessage;
use App\Message\RefreshFeedsMessage;
use App\Messenger\Middleware\ProcessedMessageMiddleware;
use App\Repository\Messages\ProcessedMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class ProcessedMessageMiddlewareTest extends TestCase
{
    #[Test]
    public function savesSuccessfulMessageWithRetentionLimit(): void
    {
        $message = new HeartbeatMessage();
        $envelope = new Envelope($message);

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function (ProcessedMessage $pm) {
                    return $pm->getMessageType() === HeartbeatMessage::class
                        && $pm->getStatus() === ProcessedMessage::STATUS_SUCCESS
                        && $pm->getErrorMessage() === null
                        && $pm->getSource() === MessageSource::Worker;
                }),
                HeartbeatMessage::getRetentionLimit(),
            );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('messages')->willReturn($em);

        $stack = $this->createMock(StackInterface::class);
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack->method('next')->willReturn($nextMiddleware);

        $middleware = new ProcessedMessageMiddleware($registry, $repository);
        $result = $middleware->handle($envelope, $stack);

        $this->assertSame($envelope, $result);
    }

    #[Test]
    public function savesFailedMessageWithErrorMessage(): void
    {
        $message = new RefreshFeedsMessage();
        $envelope = new Envelope($message);
        $exception = new \RuntimeException('Test error');

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function (ProcessedMessage $pm) {
                    return $pm->getMessageType() ===
                        RefreshFeedsMessage::class
                        && $pm->getStatus() === ProcessedMessage::STATUS_FAILED
                        && $pm->getErrorMessage() === 'Test error'
                        && $pm->getSource() === MessageSource::Manual;
                }),
                RefreshFeedsMessage::getRetentionLimit(),
            );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('messages')->willReturn($em);

        $stack = $this->createMock(StackInterface::class);
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willThrowException($exception);
        $stack->method('next')->willReturn($nextMiddleware);

        $middleware = new ProcessedMessageMiddleware($registry, $repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test error');

        $middleware->handle($envelope, $stack);
    }

    #[Test]
    public function savesMessageWithoutRetentionLimitForNonRetainableMessage(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(ProcessedMessage::class), null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManager')->with('messages')->willReturn($em);

        $stack = $this->createMock(StackInterface::class);
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack->method('next')->willReturn($nextMiddleware);

        $middleware = new ProcessedMessageMiddleware($registry, $repository);
        $middleware->handle($envelope, $stack);
    }

    #[Test]
    public function resetsEntityManagerWhenClosed(): void
    {
        $message = new HeartbeatMessage();
        $envelope = new Envelope($message);

        $repository = $this->createMock(ProcessedMessageRepository::class);
        $repository->expects($this->once())->method('save');

        $closedEm = $this->createMock(EntityManagerInterface::class);
        $closedEm->method('isOpen')->willReturn(false);

        $openEm = $this->createMock(EntityManagerInterface::class);
        $openEm->method('isOpen')->willReturn(true);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->method('getManager')
            ->with('messages')
            ->willReturn($closedEm);
        $registry
            ->expects($this->once())
            ->method('resetManager')
            ->with('messages');

        $stack = $this->createMock(StackInterface::class);
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware->method('handle')->willReturn($envelope);
        $stack->method('next')->willReturn($nextMiddleware);

        $middleware = new ProcessedMessageMiddleware($registry, $repository);
        $middleware->handle($envelope, $stack);
    }
}
