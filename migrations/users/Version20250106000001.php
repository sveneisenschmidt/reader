<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250106000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user and read_status tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) DEFAULT NULL,
            totp_secret VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_EMAIL ON user (email)');

        $this->addSql('CREATE TABLE read_status (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            feed_item_guid VARCHAR(16) NOT NULL,
            read_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_FEED_ITEM ON read_status (user_id, feed_item_guid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE read_status');
        $this->addSql('DROP TABLE user');
    }
}
