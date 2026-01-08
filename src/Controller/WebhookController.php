<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/webhook')]
class WebhookController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%env(WORKER_TTL)%')]
        private string $workerTtl,
    ) {}

    #[Route('/worker', name: 'webhook_worker', methods: ['GET', 'POST'])]
    public function worker(): Response
    {
        $process = new Process([
            'php',
            'bin/console',
            'messenger:consume',
            'scheduler_default',
            '--time-limit=' . $this->workerTtl,
        ], $this->projectDir);

        $process->setTimeout(null);
        $process->start();

        return new Response('Worker started', Response::HTTP_OK);
    }
}
