<?php

declare(strict_types=1);

namespace DoctrineMigrations\Content;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250106000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create feed_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE feed_item (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            guid VARCHAR(16) NOT NULL,
            feed_guid VARCHAR(16) NOT NULL,
            title VARCHAR(500) NOT NULL,
            link VARCHAR(1000) NOT NULL,
            source VARCHAR(255) NOT NULL,
            excerpt TEXT NOT NULL,
            published_at DATETIME NOT NULL,
            fetched_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEED_ITEM_GUID ON feed_item (guid)');
        $this->addSql('CREATE INDEX IDX_FEED_GUID ON feed_item (feed_guid)');
        $this->addSql('CREATE INDEX IDX_PUBLISHED_AT ON feed_item (published_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE feed_item');
    }
}
