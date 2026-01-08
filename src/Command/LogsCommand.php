<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use App\Entity\Logs\LogEntry;
use App\Repository\Logs\LogEntryRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: "reader:logs", description: "Display recent log entries")]
class LogsCommand extends Command
{
    public function __construct(private LogEntryRepository $logEntryRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            "channel",
            "c",
            InputOption::VALUE_REQUIRED,
            sprintf(
                "Filter by channel (%s, %s)",
                LogEntry::CHANNEL_WEBHOOK,
                LogEntry::CHANNEL_WORKER,
            ),
        )
            ->addOption(
                "status",
                "s",
                InputOption::VALUE_REQUIRED,
                sprintf(
                    "Filter by status (%s, %s)",
                    LogEntry::STATUS_SUCCESS,
                    LogEntry::STATUS_ERROR,
                ),
            )
            ->addOption(
                "limit",
                "l",
                InputOption::VALUE_REQUIRED,
                "Number of entries to show",
                20,
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $channel = $input->getOption("channel");
        $status = $input->getOption("status");
        $limit = (int) $input->getOption("limit");

        $criteria = [];
        if ($channel) {
            $criteria["channel"] = $channel;
        }
        if ($status) {
            $criteria["status"] = $status;
        }

        $entries = $this->logEntryRepository->findBy(
            $criteria,
            ["createdAt" => "DESC"],
            $limit,
        );

        if (empty($entries)) {
            $io->info("No log entries found.");
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                $entry->getCreatedAt()->format("Y-m-d H:i:s"),
                $entry->getChannel(),
                $entry->getAction(),
                $entry->getStatus(),
                $entry->getMessage() ? substr($entry->getMessage(), 0, 50) : "",
            ];
        }

        $io->table(["Time", "Channel", "Action", "Status", "Message"], $rows);

        return Command::SUCCESS;
    }
}
