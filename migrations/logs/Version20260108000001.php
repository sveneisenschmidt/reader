<?php

declare(strict_types=1);

namespace DoctrineMigrations\Logs;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260108000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create log_entry table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE log_entry (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            channel VARCHAR(50) NOT NULL,
            action VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL,
            message TEXT,
            created_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE INDEX idx_channel ON log_entry (channel)');
        $this->addSql('CREATE INDEX idx_created_at ON log_entry (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE log_entry');
    }
}
