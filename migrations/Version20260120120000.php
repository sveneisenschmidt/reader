<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_refresh_duration column to subscription table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD last_refresh_duration INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP COLUMN last_refresh_duration');
    }
}
