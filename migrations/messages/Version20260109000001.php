<?php

declare(strict_types=1);

namespace DoctrineMigrations\Messages;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260109000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create processed_messages table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE processed_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_type VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            error_message TEXT,
            processed_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE INDEX idx_message_type ON processed_messages (message_type)');
        $this->addSql('CREATE INDEX idx_processed_at ON processed_messages (processed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_messages');
    }
}
