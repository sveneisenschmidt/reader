<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260114101138 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Add indexes for better query performance";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE INDEX idx_user_id ON subscription (user_id)");
        $this->addSql(
            "CREATE INDEX idx_read_feed_item_guid ON read_status (feed_item_guid)",
        );
        $this->addSql(
            "CREATE INDEX idx_seen_feed_item_guid ON seen_status (feed_item_guid)",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX idx_user_id");
        $this->addSql("DROP INDEX idx_read_feed_item_guid");
        $this->addSql("DROP INDEX idx_seen_feed_item_guid");
    }
}
