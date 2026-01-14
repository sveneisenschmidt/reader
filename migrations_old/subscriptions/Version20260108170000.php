<?php

declare(strict_types=1);

namespace DoctrineMigrations\Subscriptions;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change folder column from JSON to VARCHAR';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription RENAME COLUMN folder TO folder_old');
        $this->addSql('ALTER TABLE subscription ADD COLUMN folder VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE subscription SET folder = folder_old');
        $this->addSql('ALTER TABLE subscription DROP COLUMN folder_old');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription RENAME COLUMN folder TO folder_old');
        $this->addSql('ALTER TABLE subscription ADD COLUMN folder JSON DEFAULT NULL');
        $this->addSql('UPDATE subscription SET folder = folder_old');
        $this->addSql('ALTER TABLE subscription DROP COLUMN folder_old');
    }
}
