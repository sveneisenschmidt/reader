<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use App\Entity\ProcessedMessage;
use App\Enum\MessageSource;
use App\Repository\ProcessedMessageRepository;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[CodeCoverageIgnore]
#[AsCommand(name: 'reader:messages', description: 'Display processed messages')]
class MessagesCommand extends Command
{
    public function __construct(
        private ProcessedMessageRepository $processedMessageRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'type',
            't',
            InputOption::VALUE_REQUIRED,
            'Filter by message type (e.g. HeartbeatMessage)',
        )
            ->addOption(
                'status',
                's',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Filter by status (%s, %s)',
                    ProcessedMessage::STATUS_SUCCESS,
                    ProcessedMessage::STATUS_FAILED,
                ),
            )
            ->addOption(
                'source',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Filter by source (%s)',
                    implode(
                        ', ',
                        array_map(
                            fn (MessageSource $s) => $s->value,
                            MessageSource::cases(),
                        ),
                    ),
                ),
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Number of entries to show',
                20,
            )
            ->addOption(
                'tail',
                'f',
                InputOption::VALUE_NONE,
                'Continuously display last 10 messages with stats header',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('tail')) {
            return $this->executeTail($io);
        }

        return $this->executeList($input, $io);
    }

    private function executeList(InputInterface $input, SymfonyStyle $io): int
    {
        $type = $input->getOption('type');
        $status = $input->getOption('status');
        $source = $input->getOption('source');
        $limit = (int) $input->getOption('limit');

        $criteria = [];
        if ($type) {
            $criteria['messageType'] = $type;
        }
        if ($status) {
            $criteria['status'] = $status;
        }
        if ($source) {
            $criteria['source'] = $source;
        }

        $entries = $this->processedMessageRepository->findBy(
            $criteria,
            ['processedAt' => 'DESC'],
            $limit,
        );

        if (empty($entries)) {
            $io->info('No processed messages found.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Time', 'Type', 'Source', 'Status', 'Error'],
            $this->formatRows($entries),
        );

        return Command::SUCCESS;
    }

    private function executeTail(SymfonyStyle $io): int
    {
        while (true) {
            $output = "\033[2J\033[H"; // Clear screen and move cursor to top

            // Get counts per type
            $counts = $this->processedMessageRepository->getCountsByType();
            $statsLine = [];
            foreach ($counts as $type => $count) {
                $shortType = substr($type, strrpos($type, '\\') + 1);
                $statsLine[] = sprintf('%s: %d', $shortType, $count);
            }

            $output .=
                'Messages: '.
                (empty($statsLine) ? 'none' : implode(' | ', $statsLine)).
                "\n";
            $output .= str_repeat('-', 80)."\n\n";

            // Get last 10 entries
            $entries = $this->processedMessageRepository->findBy(
                [],
                ['processedAt' => 'DESC'],
                10,
            );

            if (empty($entries)) {
                $output .= "No processed messages found.\n";
            } else {
                $output .= sprintf(
                    "%-19s  %-25s  %-8s  %-8s  %s\n",
                    'Time',
                    'Type',
                    'Source',
                    'Status',
                    'Error',
                );
                $output .= sprintf(
                    "%-19s  %-25s  %-8s  %-8s  %s\n",
                    str_repeat('-', 19),
                    str_repeat('-', 25),
                    str_repeat('-', 8),
                    str_repeat('-', 8),
                    str_repeat('-', 20),
                );

                foreach ($entries as $entry) {
                    $shortType = substr(
                        $entry->getMessageType(),
                        strrpos($entry->getMessageType(), '\\') + 1,
                    );
                    $output .= sprintf(
                        "%-19s  %-25s  %-8s  %-8s  %s\n",
                        $entry->getProcessedAt()->format('Y-m-d H:i:s'),
                        $shortType,
                        (string) $entry->getSource()?->value,
                        $entry->getStatus(),
                        $entry->getErrorMessage()
                            ? substr($entry->getErrorMessage(), 0, 20)
                            : '',
                    );
                }
            }

            $io->write($output);
            sleep(2);
        }
    }

    /**
     * @param ProcessedMessage[] $entries
     *
     * @return array<array<string|null>>
     */
    private function formatRows(array $entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            $shortType = substr(
                $entry->getMessageType(),
                strrpos($entry->getMessageType(), '\\') + 1,
            );
            $rows[] = [
                $entry->getProcessedAt()->format('Y-m-d H:i:s'),
                $shortType,
                $entry->getSource()?->value,
                $entry->getStatus(),
                $entry->getErrorMessage()
                    ? substr($entry->getErrorMessage(), 0, 50)
                    : '',
            ];
        }

        return $rows;
    }
}
