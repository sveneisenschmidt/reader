<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Command;

use App\Entity\ProcessedMessage;
use App\Repository\ProcessedMessageRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class MessagesCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private ProcessedMessageRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('reader:messages');
        $this->commandTester = new CommandTester($command);

        $this->repository = static::getContainer()->get(
            ProcessedMessageRepository::class,
        );

        $this->clearMessages();
    }

    private function clearMessages(): void
    {
        $em = $this->repository->getEntityManager();
        $em->createQuery("DELETE FROM App\Entity\ProcessedMessage")->execute();
    }

    #[Test]
    public function commandShowsNoEntriesMessage(): void
    {
        $this->commandTester->execute([]);

        $this->assertStringContainsString(
            'No processed messages found',
            $this->commandTester->getDisplay(),
        );
    }

    #[Test]
    public function commandShowsMessages(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\RefreshFeedsMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('RefreshFeedsMessage', $output);
        $this->assertStringContainsString('success', $output);
    }

    #[Test]
    public function commandFiltersByType(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\RefreshFeedsMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        $this->repository->save(new ProcessedMessage(
            'App\Message\HeartbeatMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));

        $this->commandTester->execute(['--type' => 'App\Message\RefreshFeedsMessage']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('RefreshFeedsMessage', $output);
        $this->assertStringNotContainsString('HeartbeatMessage', $output);
    }

    #[Test]
    public function commandFiltersByStatus(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\RefreshFeedsMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        $this->repository->save(new ProcessedMessage(
            'App\Message\CleanupContentMessage',
            ProcessedMessage::STATUS_FAILED,
            'Some error',
        ));

        $this->commandTester->execute(['--status' => 'failed']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('failed', $output);
        $this->assertStringContainsString('CleanupContentMessage', $output);
        $this->assertStringNotContainsString('RefreshFeedsMessage', $output);
    }

    #[Test]
    public function commandRespectsLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->repository->save(new ProcessedMessage(
                'App\Message\HeartbeatMessage'.$i,
                ProcessedMessage::STATUS_SUCCESS,
            ));
        }

        $this->commandTester->execute(['--limit' => '2']);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('HeartbeatMessage4', $output);
        $this->assertStringContainsString('HeartbeatMessage3', $output);
        $this->assertStringNotContainsString('HeartbeatMessage0', $output);
    }

    #[Test]
    public function commandCombinesFilters(): void
    {
        $this->repository->save(new ProcessedMessage(
            'App\Message\RefreshFeedsMessage',
            ProcessedMessage::STATUS_SUCCESS,
        ));
        $this->repository->save(new ProcessedMessage(
            'App\Message\RefreshFeedsMessage',
            ProcessedMessage::STATUS_FAILED,
            'Error message',
        ));
        $this->repository->save(new ProcessedMessage(
            'App\Message\HeartbeatMessage',
            ProcessedMessage::STATUS_FAILED,
            'Worker error',
        ));

        $this->commandTester->execute([
            '--type' => 'App\Message\RefreshFeedsMessage',
            '--status' => 'failed',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('RefreshFeedsMessage', $output);
        $this->assertStringContainsString('failed', $output);
        $this->assertStringNotContainsString('HeartbeatMessage', $output);
    }
}
