<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113061024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove theme and keyboard_shortcuts columns from user table (moved to user_preference)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TEMPORARY TABLE __temp__user AS SELECT id, username, email, password, totp_secret, created_at FROM user',
        );
        $this->addSql('DROP TABLE user');
        $this->addSql(
            'CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)',
        );
        $this->addSql(
            'INSERT INTO user (id, username, email, password, totp_secret, created_at) SELECT id, username, email, password, totp_secret, created_at FROM __temp__user',
        );
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE user ADD COLUMN theme VARCHAR(10) DEFAULT \'auto\' NOT NULL',
        );
        $this->addSql(
            'ALTER TABLE user ADD COLUMN keyboard_shortcuts BOOLEAN DEFAULT 0 NOT NULL',
        );
    }
}
