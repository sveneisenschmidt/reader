<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Command;

use App\Entity\Logs\LogEntry;
use App\Repository\Logs\LogEntryRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LogsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private LogEntryRepository $logEntryRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find("reader:logs");
        $this->commandTester = new CommandTester($command);

        $this->logEntryRepository = static::getContainer()->get(
            LogEntryRepository::class,
        );

        $this->clearLogEntries();
    }

    private function clearLogEntries(): void
    {
        $em = $this->logEntryRepository->getEntityManager();
        $em->createQuery("DELETE FROM App\Entity\Logs\LogEntry")->execute();
    }

    #[Test]
    public function commandShowsNoEntriesMessage(): void
    {
        $this->commandTester->execute([]);

        $this->assertStringContainsString(
            "No log entries found",
            $this->commandTester->getDisplay(),
        );
    }

    #[Test]
    public function commandShowsLogEntries(): void
    {
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WEBHOOK,
            "refresh-feeds",
            LogEntry::STATUS_SUCCESS,
        );

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("webhook", $output);
        $this->assertStringContainsString("refresh-feeds", $output);
        $this->assertStringContainsString("success", $output);
    }

    #[Test]
    public function commandFiltersbyChannel(): void
    {
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WEBHOOK,
            "refresh-feeds",
            LogEntry::STATUS_SUCCESS,
        );
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WORKER,
            "heartbeat",
            LogEntry::STATUS_SUCCESS,
        );

        $this->commandTester->execute(["--channel" => "webhook"]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("webhook", $output);
        $this->assertStringNotContainsString("worker", $output);
    }

    #[Test]
    public function commandFiltersByStatus(): void
    {
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WEBHOOK,
            "refresh-feeds",
            LogEntry::STATUS_SUCCESS,
        );
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WEBHOOK,
            "cleanup-content",
            LogEntry::STATUS_ERROR,
            "Some error",
        );

        $this->commandTester->execute(["--status" => "error"]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("error", $output);
        $this->assertStringContainsString("cleanup-content", $output);
        $this->assertStringNotContainsString("refresh-feeds", $output);
    }

    #[Test]
    public function commandRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->logEntryRepository->log(
                LogEntry::CHANNEL_WORKER,
                "heartbeat-" . $i,
                LogEntry::STATUS_SUCCESS,
            );
        }

        $this->commandTester->execute(["--limit" => "2"]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("heartbeat-4", $output);
        $this->assertStringContainsString("heartbeat-3", $output);
        $this->assertStringNotContainsString("heartbeat-0", $output);
    }

    #[Test]
    public function commandCombinesFilters(): void
    {
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WEBHOOK,
            "refresh-feeds",
            LogEntry::STATUS_SUCCESS,
        );
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WEBHOOK,
            "cleanup-content",
            LogEntry::STATUS_ERROR,
            "Error message",
        );
        $this->logEntryRepository->log(
            LogEntry::CHANNEL_WORKER,
            "heartbeat",
            LogEntry::STATUS_ERROR,
            "Worker error",
        );

        $this->commandTester->execute([
            "--channel" => "webhook",
            "--status" => "error",
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("cleanup-content", $output);
        $this->assertStringNotContainsString("refresh-feeds", $output);
        $this->assertStringNotContainsString("heartbeat", $output);
    }
}
