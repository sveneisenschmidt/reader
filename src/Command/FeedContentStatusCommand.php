<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use App\Repository\Content\FeedItemRepository;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

#[CodeCoverageIgnore]
#[When(env: 'dev')]
#[
    AsCommand(
        name: 'reader:feed-status',
        description: 'Show status of the feed content repository',
    ),
]
class FeedContentStatusCommand extends Command
{
    public function __construct(private FeedItemRepository $feedItemRepository)
    {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $allItems = $this->feedItemRepository->findAll();
        $totalCount = count($allItems);

        $io->title('Feed Content Repository Status');

        $io->table(['Metric', 'Value'], [['Total items', $totalCount]]);

        if ($totalCount > 0) {
            // Group by feed
            $feedCounts = [];
            foreach ($allItems as $item) {
                $source = $item->getSource();
                if (!isset($feedCounts[$source])) {
                    $feedCounts[$source] = 0;
                }
                ++$feedCounts[$source];
            }

            $io->section('Items per Feed');
            $rows = [];
            foreach ($feedCounts as $source => $count) {
                $rows[] = [$source, $count];
            }
            $io->table(['Feed', 'Items'], $rows);

            // Latest items
            $io->section('Latest 5 Items');
            $latest = array_slice($allItems, 0, 5);
            $rows = [];
            foreach ($latest as $item) {
                $rows[] = [
                    substr($item->getTitle(), 0, 50).
                    (strlen($item->getTitle()) > 50 ? '...' : ''),
                    $item->getSource(),
                    $item->getPublishedAt()->format('Y-m-d H:i'),
                ];
            }
            $io->table(['Title', 'Source', 'Published'], $rows);
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }
}
