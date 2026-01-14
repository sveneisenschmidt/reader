<?php

declare(strict_types=1);

namespace DoctrineMigrations\Subscriptions;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106183029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, user_id, url, name, guid, created_at FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, url VARCHAR(500) NOT NULL, name VARCHAR(255) NOT NULL, guid VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, last_refreshed_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO subscription (id, user_id, url, name, guid, created_at) SELECT id, user_id, url, name, guid, created_at FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE UNIQUE INDEX user_url ON subscription (user_id, url)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__subscription AS SELECT id, user_id, url, name, guid, created_at FROM subscription');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, url VARCHAR(500) NOT NULL, name VARCHAR(255) NOT NULL, guid VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, display_mode VARCHAR(10) DEFAULT \'excerpt\' NOT NULL)');
        $this->addSql('INSERT INTO subscription (id, user_id, url, name, guid, created_at) SELECT id, user_id, url, name, guid, created_at FROM __temp__subscription');
        $this->addSql('DROP TABLE __temp__subscription');
        $this->addSql('CREATE UNIQUE INDEX user_url ON subscription (user_id, url)');
    }
}
