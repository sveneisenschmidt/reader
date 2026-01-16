<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bookmark_status table for saving articles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE bookmark_status (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                feed_item_guid VARCHAR(16) NOT NULL,
                bookmarked_at DATETIME NOT NULL
            )
        ');
        $this->addSql(
            'CREATE UNIQUE INDEX user_bookmarked_item ON bookmark_status (user_id, feed_item_guid)',
        );
        $this->addSql(
            'CREATE INDEX idx_bookmark_feed_item_guid ON bookmark_status (feed_item_guid)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bookmark_status');
    }
}
