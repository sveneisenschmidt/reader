<?php

declare(strict_types=1);

namespace DoctrineMigrations\Content;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250113000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename feed_guid to subscription_guid in feed_item table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feed_item RENAME COLUMN feed_guid TO subscription_guid');
        $this->addSql('DROP INDEX IDX_FEED_GUID');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_GUID ON feed_item (subscription_guid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE feed_item RENAME COLUMN subscription_guid TO feed_guid');
        $this->addSql('DROP INDEX IDX_SUBSCRIPTION_GUID');
        $this->addSql('CREATE INDEX IDX_FEED_GUID ON feed_item (feed_guid)');
    }
}
