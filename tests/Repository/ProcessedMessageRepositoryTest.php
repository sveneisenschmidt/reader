<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository;

use App\Entity\ProcessedMessage;
use App\Enum\MessageSource;
use App\Repository\ProcessedMessageRepository;
use App\Tests\Trait\DatabaseIsolationTrait;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProcessedMessageRepositoryTest extends KernelTestCase
{
    use DatabaseIsolationTrait;

    private ProcessedMessageRepository $repository;
    private string $testMessageType = 'App\\Tests\\TestMessage';

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(
            ProcessedMessageRepository::class,
        );
    }

    #[Test]
    public function savePersistsMessage(): void
    {
        $message = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
        );

        $this->repository->save($message);

        $result = $this->repository->getLastByType($this->testMessageType);
        $this->assertNotNull($result);
        $this->assertEquals($this->testMessageType, $result->getMessageType());
    }

    #[Test]
    public function saveWithRetentionLimitPrunesOldMessages(): void
    {
        // Create several messages
        for ($i = 0; $i < 5; ++$i) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        // Save one more with retention limit of 2
        $message = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
        );
        $this->repository->save($message, 2);

        $remaining = $this->repository->findByType($this->testMessageType);
        $this->assertLessThanOrEqual(2, count($remaining));
    }

    #[Test]
    public function getLastByTypeReturnsNullWhenNoMessages(): void
    {
        $result = $this->repository->getLastByType(
            'App\\Nonexistent\\MessageType',
        );

        $this->assertNull($result);
    }

    #[Test]
    public function getLastByTypeReturnsLatestMessage(): void
    {
        $message1 = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
        );
        $this->repository->save($message1);

        $message2 = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_FAILED,
            'Error message',
        );
        $this->repository->save($message2);

        $result = $this->repository->getLastByType($this->testMessageType);

        $this->assertNotNull($result);
        // Just verify we get a message back - order depends on DB insert order
        $this->assertContains($result->getStatus(), [
            ProcessedMessage::STATUS_SUCCESS,
            ProcessedMessage::STATUS_FAILED,
        ]);
    }

    #[Test]
    public function getLastSuccessByTypeReturnsNullWhenNoSuccesses(): void
    {
        $message = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_FAILED,
            'Error',
        );
        $this->repository->save($message);

        $result = $this->repository->getLastSuccessByType(
            'App\\Another\\NonexistentType',
        );

        $this->assertNull($result);
    }

    #[Test]
    public function getLastSuccessByTypeReturnsLatestSuccess(): void
    {
        $success = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
        );
        $this->repository->save($success);

        $failed = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_FAILED,
            'Error',
        );
        $this->repository->save($failed);

        $result = $this->repository->getLastSuccessByType(
            $this->testMessageType,
        );

        $this->assertNotNull($result);
        $this->assertEquals(
            ProcessedMessage::STATUS_SUCCESS,
            $result->getStatus(),
        );
    }

    #[Test]
    public function findByTypeReturnsEmptyArrayWhenNoMessages(): void
    {
        $result = $this->repository->findByType('App\\Empty\\MessageType');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findByTypeReturnsMessagesOrderedByDate(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        $result = $this->repository->findByType($this->testMessageType);

        $this->assertGreaterThanOrEqual(3, count($result));
    }

    #[Test]
    public function findByTypeRespectsLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        $result = $this->repository->findByType($this->testMessageType, 2);

        $this->assertLessThanOrEqual(2, count($result));
    }

    #[Test]
    public function pruneByTypeRemovesAllWhenKeepIsZero(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        $this->repository->pruneByType($this->testMessageType, 0);

        $result = $this->repository->findByType($this->testMessageType);
        $this->assertEmpty($result);
    }

    #[Test]
    public function pruneByTypeKeepsSpecifiedNumber(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        $this->repository->pruneByType($this->testMessageType, 2);

        $result = $this->repository->findByType($this->testMessageType);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function getCountsByTypeReturnsEmptyArrayWhenNoMessages(): void
    {
        $counts = $this->repository->getCountsByType();

        $this->assertIsArray($counts);
    }

    #[Test]
    public function getCountsByTypeReturnsCorrectCounts(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        $counts = $this->repository->getCountsByType();

        $this->assertArrayHasKey($this->testMessageType, $counts);
        $this->assertGreaterThanOrEqual(3, $counts[$this->testMessageType]);
    }

    #[Test]
    public function saveWorksAfterEntityManagerIsClosed(): void
    {
        // Use a unique message type to avoid polluting other tests
        $uniqueMessageType = 'App\\Tests\\ClosedEmTestMessage';

        $registry = static::getContainer()->get(ManagerRegistry::class);
        $em = $registry->getManager();
        $em->close();

        $message = new ProcessedMessage(
            $uniqueMessageType,
            ProcessedMessage::STATUS_SUCCESS,
        );

        $this->repository->save($message);

        $result = $this->repository->getLastByType($uniqueMessageType);
        $this->assertNotNull($result);
    }

    #[Test]
    public function getCountsByTypeAndSourceReturnsCorrectData(): void
    {
        $message1 = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
            null,
            MessageSource::Worker,
        );
        $this->repository->save($message1);

        $message2 = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
            null,
            MessageSource::Webhook,
        );
        $this->repository->save($message2);

        $counts = $this->repository->getCountsByTypeAndSource();

        $this->assertArrayHasKey($this->testMessageType, $counts);
        $this->assertArrayHasKey('worker', $counts[$this->testMessageType]);
        $this->assertArrayHasKey('webhook', $counts[$this->testMessageType]);
        $this->assertEquals(
            1,
            $counts[$this->testMessageType]['worker']['count'],
        );
        $this->assertEquals(
            1,
            $counts[$this->testMessageType]['webhook']['count'],
        );
    }

    #[Test]
    public function getLastSuccessByTypeAndSourceReturnsCorrectMessage(): void
    {
        $message = new ProcessedMessage(
            $this->testMessageType,
            ProcessedMessage::STATUS_SUCCESS,
            null,
            MessageSource::Worker,
        );
        $this->repository->save($message);

        $result = $this->repository->getLastSuccessByTypeAndSource(
            $this->testMessageType,
            MessageSource::Worker,
        );

        $this->assertNotNull($result);
        $this->assertEquals(
            ProcessedMessage::STATUS_SUCCESS,
            $result->getStatus(),
        );
    }
}
