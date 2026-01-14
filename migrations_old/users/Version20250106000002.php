<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250106000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create seen_status table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE seen_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            feed_item_guid VARCHAR(16) NOT NULL,
            seen_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_SEEN_USER_FEED_ITEM ON seen_status (user_id, feed_item_guid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE seen_status');
    }
}
