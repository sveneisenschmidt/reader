<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114092826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__feed_item AS SELECT id, guid, subscription_guid, title, link, source, excerpt, published_at, fetched_at FROM feed_item');
        $this->addSql('DROP TABLE feed_item');
        $this->addSql('CREATE TABLE feed_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, guid VARCHAR(16) NOT NULL, subscription_guid VARCHAR(16) NOT NULL, title VARCHAR(500) NOT NULL, link VARCHAR(1000) NOT NULL, source VARCHAR(255) NOT NULL, excerpt CLOB NOT NULL, published_at DATETIME NOT NULL, fetched_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO feed_item (id, guid, subscription_guid, title, link, source, excerpt, published_at, fetched_at) SELECT id, guid, subscription_guid, title, link, source, excerpt, published_at, fetched_at FROM __temp__feed_item');
        $this->addSql('DROP TABLE __temp__feed_item');
        $this->addSql('CREATE INDEX idx_published_at ON feed_item (published_at)');
        $this->addSql('CREATE INDEX idx_subscription_guid ON feed_item (subscription_guid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F8CCE492B6FCFB2 ON feed_item (guid)');
        $this->addSql('CREATE INDEX idx_fetched_at ON feed_item (fetched_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__feed_item AS SELECT id, guid, subscription_guid, title, link, source, excerpt, published_at, fetched_at FROM feed_item');
        $this->addSql('DROP TABLE feed_item');
        $this->addSql('CREATE TABLE feed_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, guid VARCHAR(16) NOT NULL, subscription_guid VARCHAR(16) NOT NULL, title VARCHAR(500) NOT NULL, link VARCHAR(1000) NOT NULL, source VARCHAR(255) NOT NULL, excerpt CLOB NOT NULL, published_at DATETIME NOT NULL, fetched_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO feed_item (id, guid, subscription_guid, title, link, source, excerpt, published_at, fetched_at) SELECT id, guid, subscription_guid, title, link, source, excerpt, published_at, fetched_at FROM __temp__feed_item');
        $this->addSql('DROP TABLE __temp__feed_item');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F8CCE492B6FCFB2 ON feed_item (guid)');
        $this->addSql('CREATE INDEX idx_subscription_guid ON feed_item (subscription_guid)');
        $this->addSql('CREATE INDEX idx_published_at ON feed_item (published_at)');
    }
}
