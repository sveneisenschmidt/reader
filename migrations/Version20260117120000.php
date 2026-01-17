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

final class Version20260117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add use_archive_is column to subscription table for archive.is support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD use_archive_is BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP COLUMN use_archive_is');
    }
}
