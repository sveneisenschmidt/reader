<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Repository;

use App\Entity\ProcessedMessage;
use App\Repository\ProcessedMessageRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProcessedMessageRepositoryTest extends KernelTestCase
{
    private ProcessedMessageRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(ProcessedMessageRepository::class);
        $this->clearMessages();
    }

    private function clearMessages(): void
    {
        $em = $this->repository->getEntityManager();
        $em->createQuery("DELETE FROM App\Entity\ProcessedMessage")->execute();
    }

    #[Test]
    public function savesPersistsMessage(): void
    {
        $message = new ProcessedMessage(
            'App\Message\TestMessage',
            ProcessedMessage::STATUS_SUCCESS,
        );

        $this->repository->save($message);

        $this->assertNotNull($message->getId());
    }

    #[Test]
    public function getLastByTypeReturnsLatestMessage(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\TestMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        sleep(1);
        $this->repository->save(new ProcessedMessage(
            'App\Message\TestMessage',
            ProcessedMessage::STATUS_FAILED,
            'Error',
        ));

        $last = $this->repository->getLastByType('App\Message\TestMessage');

        $this->assertNotNull($last);
        $this->assertEquals(ProcessedMessage::STATUS_FAILED, $last->getStatus());
    }

    #[Test]
    public function getLastByTypeReturnsNullWhenNoMessages(): void
    {
        $last = $this->repository->getLastByType('App\Message\NonExistent');

        $this->assertNull($last);
    }

    #[Test]
    public function getLastSuccessByTypeReturnsLatestSuccessfulMessage(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\TestMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        sleep(1);
        $this->repository->save(new ProcessedMessage(
            'App\Message\TestMessage',
            ProcessedMessage::STATUS_FAILED,
            'Error',
        ));

        $last = $this->repository->getLastSuccessByType('App\Message\TestMessage');

        $this->assertNotNull($last);
        $this->assertEquals(ProcessedMessage::STATUS_SUCCESS, $last->getStatus());
    }

    #[Test]
    public function getLastSuccessByTypeReturnsNullWhenOnlyFailedMessages(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\TestMessage',
            ProcessedMessage::STATUS_FAILED,
            'Error',
        ));

        $last = $this->repository->getLastSuccessByType('App\Message\TestMessage');

        $this->assertNull($last);
    }

    #[Test]
    public function findByTypeReturnsMessagesOfType(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\TypeA',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        $this->repository->save(new ProcessedMessage(
            'App\Message\TypeB',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        $this->repository->save(new ProcessedMessage(
            'App\Message\TypeA',
            ProcessedMessage::STATUS_SUCCESS,
        ));

        $messages = $this->repository->findByType('App\Message\TypeA');

        $this->assertCount(2, $messages);
    }

    #[Test]
    public function findByTypeRespectsLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->repository->save(new ProcessedMessage(
                'App\Message\TestMessage',
                ProcessedMessage::STATUS_SUCCESS,
            ));
        }

        $messages = $this->repository->findByType('App\Message\TestMessage', 3);

        $this->assertCount(3, $messages);
    }

    #[Test]
    public function savePrunesOldMessagesWhenRetentionLimitSet(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->repository->save(new ProcessedMessage(
                'App\Message\TestMessage',
                ProcessedMessage::STATUS_SUCCESS,
            ));
        }

        $this->repository->save(
            new ProcessedMessage(
                'App\Message\TestMessage',
                ProcessedMessage::STATUS_SUCCESS,
            ),
            3 // Keep only 3
        );

        $messages = $this->repository->findByType('App\Message\TestMessage');

        $this->assertCount(3, $messages);
    }

    #[Test]
    public function savePrunesOnlyMessagesOfSameType(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->repository->save(new ProcessedMessage(
                'App\Message\TypeA',
                ProcessedMessage::STATUS_SUCCESS,
            ));
        }
        $this->repository->save(new ProcessedMessage(
            'App\Message\TypeB',
            ProcessedMessage::STATUS_SUCCESS,
        ));

        $this->repository->save(
            new ProcessedMessage(
                'App\Message\TypeA',
                ProcessedMessage::STATUS_SUCCESS,
            ),
            2
        );

        $typeAMessages = $this->repository->findByType('App\Message\TypeA');
        $typeBMessages = $this->repository->findByType('App\Message\TypeB');

        $this->assertCount(2, $typeAMessages);
        $this->assertCount(1, $typeBMessages);
    }
}
