<?php

declare(strict_types=1);

namespace DoctrineMigrations\Subscriptions;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status column to track subscription refresh status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE subscription ADD COLUMN status VARCHAR(20) DEFAULT 'pending' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP COLUMN status');
    }
}
