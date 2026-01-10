<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Command;

use App\Service\EncryptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[
    AsCommand(
        name: "reader:encrypt",
        description: "Encrypt a value using the APP_SECRET",
    ),
]
class EncryptCommand extends Command
{
    public function __construct(private EncryptionService $encryption)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            "value",
            InputArgument::REQUIRED,
            "The value to encrypt",
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $value = $input->getArgument("value");
        $encrypted = $this->encryption->encrypt($value);

        $output->writeln($encrypted);

        return Command::SUCCESS;
    }
}
