<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260114092826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fetched_at index to feed_item table';
    }

    public function up(Schema $schema): void
    {
        // Only create index if table exists (may not exist in fresh CI environments)
        if ($schema->hasTable('feed_item')) {
            $this->addSql(
                'CREATE INDEX IF NOT EXISTS idx_fetched_at ON feed_item (fetched_at)',
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_fetched_at');
    }
}
