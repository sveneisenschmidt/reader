<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107165758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, email, password, totp_secret, created_at, username FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(255) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, username VARCHAR(255) NOT NULL, theme VARCHAR(10) DEFAULT \'auto\' NOT NULL)');
        $this->addSql('INSERT INTO user (id, email, password, totp_secret, created_at, username) SELECT id, email, password, totp_secret, created_at, username FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__user AS SELECT id, username, email, password, totp_secret, created_at FROM user');
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(255) DEFAULT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO user (id, username, email, password, totp_secret, created_at) SELECT id, username, email, password, totp_secret, created_at FROM __temp__user');
        $this->addSql('DROP TABLE __temp__user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERNAME ON user (username)');
    }
}
