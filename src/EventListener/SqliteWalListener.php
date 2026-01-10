<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\EventListener;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CodeCoverageIgnore;

#[CodeCoverageIgnore]
class SqliteWalListener
{
    public function postConnect(ConnectionEventArgs $event): void
    {
        $connection = $event->getConnection();

        if ($connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $connection->executeStatement('PRAGMA journal_mode=WAL');
        }
    }
}
