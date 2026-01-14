<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[
    AsCommand(
        name: "reader:migrate-data",
        description: "Migrate data from old multi-database structure to single database",
    ),
]
#[CodeCoverageIgnore]
class MigrateDataCommand extends Command
{
    public function __construct(
        private Connection $connection,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            "users-db",
            null,
            InputOption::VALUE_REQUIRED,
            "Path to old users database",
            "var/data/users.db",
        )
            ->addOption(
                "subscriptions-db",
                null,
                InputOption::VALUE_REQUIRED,
                "Path to old subscriptions database",
                "var/data/subscriptions.db",
            )
            ->addOption(
                "content-db",
                null,
                InputOption::VALUE_REQUIRED,
                "Path to old content database",
                "var/data/content.db",
            )
            ->addOption(
                "messages-db",
                null,
                InputOption::VALUE_REQUIRED,
                "Path to old messages database",
                "var/data/messages.db",
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $usersDb = $this->resolvePath($input->getOption("users-db"));
        $subscriptionsDb = $this->resolvePath(
            $input->getOption("subscriptions-db"),
        );
        $contentDb = $this->resolvePath($input->getOption("content-db"));
        $messagesDb = $this->resolvePath($input->getOption("messages-db"));

        // Verify old databases exist
        $missingDbs = [];
        if (!file_exists($usersDb)) {
            $missingDbs[] = $usersDb;
        }
        if (!file_exists($subscriptionsDb)) {
            $missingDbs[] = $subscriptionsDb;
        }
        if (!file_exists($contentDb)) {
            $missingDbs[] = $contentDb;
        }
        if (!file_exists($messagesDb)) {
            $missingDbs[] = $messagesDb;
        }

        if (!empty($missingDbs)) {
            $io->error("Missing database files: " . implode(", ", $missingDbs));

            return Command::FAILURE;
        }

        $io->title("Migrating data from old databases");

        // Migrate users database (user, user_preference, read_status, seen_status)
        $io->section("Migrating users database");
        $this->migrateDatabase(
            $usersDb,
            "old_users",
            ["user", "user_preference", "read_status", "seen_status"],
            $io,
        );

        // Migrate subscriptions database
        $io->section("Migrating subscriptions database");
        $this->migrateDatabase(
            $subscriptionsDb,
            "old_subs",
            ["subscription"],
            $io,
        );

        // Migrate content database
        $io->section("Migrating content database");
        $this->migrateDatabase($contentDb, "old_content", ["feed_item"], $io);

        // Migrate messages database
        $io->section("Migrating messages database");
        $this->migrateDatabase(
            $messagesDb,
            "old_msgs",
            ["processed_message"],
            $io,
        );

        $io->success("Data migration completed successfully");

        return Command::SUCCESS;
    }

    /**
     * @param string[] $tables
     */
    private function migrateDatabase(
        string $dbPath,
        string $alias,
        array $tables,
        SymfonyStyle $io,
    ): void {
        $this->connection->executeStatement(
            sprintf("ATTACH DATABASE '%s' AS %s", $dbPath, $alias),
        );

        foreach ($tables as $table) {
            $count = $this->connection->fetchOne(
                sprintf("SELECT COUNT(*) FROM %s.%s", $alias, $table),
            );

            $this->connection->executeStatement(
                sprintf(
                    "INSERT INTO %s SELECT * FROM %s.%s",
                    $table,
                    $alias,
                    $table,
                ),
            );

            $io->writeln(sprintf("  Migrated %d rows from %s", $count, $table));
        }

        $this->connection->executeStatement(
            sprintf("DETACH DATABASE %s", $alias),
        );
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, "/")) {
            return $path;
        }

        return $this->projectDir . "/" . $path;
    }
}
