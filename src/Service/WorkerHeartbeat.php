<?php
/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WorkerHeartbeat
{
    private string $heartbeatFile;

    public function __construct(
        #[Autowire("%kernel.project_dir%")] string $projectDir,
    ) {
        $this->heartbeatFile = $projectDir . "/var/worker_heartbeat";
    }

    public function beat(): void
    {
        file_put_contents($this->heartbeatFile, (string) time());
    }

    public function getLastBeat(): ?\DateTimeImmutable
    {
        if (!file_exists($this->heartbeatFile)) {
            return null;
        }

        $timestamp = file_get_contents($this->heartbeatFile);

        if ($timestamp === false) {
            return null;
        }

        return new \DateTimeImmutable()->setTimestamp((int) $timestamp);
    }

    public function isAlive(int $maxAge = 30): bool
    {
        $lastBeat = $this->getLastBeat();

        if ($lastBeat === null) {
            return false;
        }

        return time() - $lastBeat->getTimestamp() <= $maxAge;
    }
}
