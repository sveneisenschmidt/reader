<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114065237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feed_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, guid VARCHAR(16) NOT NULL, subscription_guid VARCHAR(16) NOT NULL, title VARCHAR(500) NOT NULL, link VARCHAR(1000) NOT NULL, source VARCHAR(255) NOT NULL, excerpt CLOB NOT NULL, published_at DATETIME NOT NULL, fetched_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F8CCE492B6FCFB2 ON feed_item (guid)');
        $this->addSql('CREATE INDEX idx_subscription_guid ON feed_item (subscription_guid)');
        $this->addSql('CREATE INDEX idx_published_at ON feed_item (published_at)');
        $this->addSql('CREATE TABLE processed_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, message_type VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, error_message CLOB DEFAULT NULL, processed_at DATETIME NOT NULL, source VARCHAR(20) DEFAULT NULL)');
        $this->addSql('CREATE INDEX idx_message_type ON processed_messages (message_type)');
        $this->addSql('CREATE INDEX idx_processed_at ON processed_messages (processed_at)');
        $this->addSql('CREATE INDEX idx_source ON processed_messages (source)');
        $this->addSql('CREATE TABLE read_status (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, feed_item_guid VARCHAR(16) NOT NULL, read_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX user_feed_item ON read_status (user_id, feed_item_guid)');
        $this->addSql('CREATE TABLE seen_status (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, feed_item_guid VARCHAR(16) NOT NULL, seen_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX user_feed_item_seen ON seen_status (user_id, feed_item_guid)');
        $this->addSql('CREATE TABLE subscription (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, url VARCHAR(500) NOT NULL, name VARCHAR(255) NOT NULL, guid VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, last_refreshed_at DATETIME DEFAULT NULL, folder VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX user_url ON subscription (user_id, url)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('CREATE TABLE user_preference (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, preference_key VARCHAR(50) NOT NULL, value CLOB NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX user_preference_key ON user_preference (user_id, preference_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE feed_item');
        $this->addSql('DROP TABLE processed_messages');
        $this->addSql('DROP TABLE read_status');
        $this->addSql('DROP TABLE seen_status');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_preference');
    }
}
