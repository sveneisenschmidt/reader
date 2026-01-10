<?php

declare(strict_types=1);

namespace DoctrineMigrations\Messages;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add source column to processed_messages table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE processed_messages ADD COLUMN source VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_source ON processed_messages (source)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_source');
        $this->addSql('ALTER TABLE processed_messages DROP COLUMN source');
    }
}
