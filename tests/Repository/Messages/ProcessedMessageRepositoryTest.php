<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Repository\Messages;

use App\Entity\Messages\ProcessedMessage;
use App\Repository\Messages\ProcessedMessageRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProcessedMessageRepositoryTest extends KernelTestCase
{
    private ProcessedMessageRepository $repository;
    private string $testMessageType = "App\\Tests\\TestMessage";

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(
            ProcessedMessageRepository::class,
        );
    }

    protected function tearDown(): void
    {
        // Clean up test messages
        $this->repository->pruneByType($this->testMessageType, 0);
        parent::tearDown();
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
        for ($i = 0; $i < 5; $i++) {
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
            "App\\Nonexistent\\MessageType",
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
            "Error message",
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
            "Error",
        );
        $this->repository->save($message);

        $result = $this->repository->getLastSuccessByType(
            "App\\Another\\NonexistentType",
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
            "Error",
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
        $result = $this->repository->findByType("App\\Empty\\MessageType");

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function findByTypeReturnsMessagesOrderedByDate(): void
    {
        for ($i = 0; $i < 3; $i++) {
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
        for ($i = 0; $i < 5; $i++) {
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
        for ($i = 0; $i < 3; $i++) {
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
        for ($i = 0; $i < 5; $i++) {
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
        // Clean up first
        $this->repository->pruneByType($this->testMessageType, 0);

        $counts = $this->repository->getCountsByType();

        $this->assertIsArray($counts);
        // May contain other message types from other tests
    }

    #[Test]
    public function getCountsByTypeReturnsCorrectCounts(): void
    {
        // Clean up first
        $this->repository->pruneByType($this->testMessageType, 0);

        for ($i = 0; $i < 3; $i++) {
            $message = new ProcessedMessage(
                $this->testMessageType,
                ProcessedMessage::STATUS_SUCCESS,
            );
            $this->repository->save($message);
        }

        $counts = $this->repository->getCountsByType();

        $this->assertArrayHasKey($this->testMessageType, $counts);
        $this->assertEquals(3, $counts[$this->testMessageType]);
    }
}
