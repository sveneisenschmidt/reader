<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Trait;

use Doctrine\DBAL\Connection;

/**
 * Trait for database isolation in tests using transaction rollback.
 *
 * This trait wraps each test in a database transaction that is rolled back
 * after the test completes, ensuring complete isolation between tests.
 *
 * Usage: Add `use DatabaseIsolationTrait;` to your KernelTestCase or WebTestCase.
 * For KernelTestCase, the kernel will be booted automatically in setUp().
 */
trait DatabaseIsolationTrait
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->beginDatabaseTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollbackDatabaseTransaction();
        parent::tearDown();
    }

    private function beginDatabaseTransaction(): void
    {
        $connection = $this->getDatabaseConnection();
        if ($connection !== null && !$connection->isTransactionActive()) {
            $connection->beginTransaction();
        }
    }

    private function rollbackDatabaseTransaction(): void
    {
        $connection = $this->getDatabaseConnection();
        if ($connection === null) {
            return;
        }

        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    private function getDatabaseConnection(): ?Connection
    {
        try {
            $container = static::getContainer();

            return $container->get('doctrine')->getConnection();
        } catch (\Throwable) {
            return null;
        }
    }
}
