<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260112153348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add keyboard_shortcuts column to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE user ADD COLUMN keyboard_shortcuts BOOLEAN DEFAULT 0 NOT NULL',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP COLUMN keyboard_shortcuts');
    }
}
