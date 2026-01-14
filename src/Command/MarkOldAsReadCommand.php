<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use App\Repository\FeedItemRepository;
use App\Repository\UserRepository;
use App\Service\ReadStatusService;
use App\Service\SubscriptionService;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[CodeCoverageIgnore]
#[
    AsCommand(
        name: 'reader:mark-old-as-read',
        description: 'Mark all feed items older than today as read',
    ),
]
class MarkOldAsReadCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private SubscriptionService $subscriptionService,
        private FeedItemRepository $feedItemRepository,
        private ReadStatusService $readStatusService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'user',
            'u',
            InputOption::VALUE_REQUIRED,
            'The user email',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $email = $input->getOption('user');
        if (!$email) {
            $output->writeln(
                '<error>Please provide a user email with --user</error>',
            );

            return Command::FAILURE;
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            $output->writeln('<error>User not found</error>');

            return Command::FAILURE;
        }

        $userId = $user->getId();
        $today = new \DateTimeImmutable('today');

        $subscriptionGuids = $this->subscriptionService->getSubscriptionGuids(
            $userId,
        );
        if (empty($subscriptionGuids)) {
            $output->writeln('No subscriptions found for user');

            return Command::SUCCESS;
        }

        $feedItems = $this->feedItemRepository->findBySubscriptionGuids(
            $subscriptionGuids,
        );
        $alreadyRead = $this->readStatusService->getReadGuidsForUser($userId);
        $alreadyReadSet = array_flip($alreadyRead);

        $toMarkAsRead = [];
        foreach ($feedItems as $item) {
            if (
                $item->getPublishedAt() < $today
                && !isset($alreadyReadSet[$item->getGuid()])
            ) {
                $toMarkAsRead[] = $item->getGuid();
            }
        }

        if (empty($toMarkAsRead)) {
            $output->writeln('No items to mark as read');

            return Command::SUCCESS;
        }

        $this->readStatusService->markManyAsRead($userId, $toMarkAsRead);
        $output->writeln(
            sprintf('Marked %d items as read', count($toMarkAsRead)),
        );

        return Command::SUCCESS;
    }
}
