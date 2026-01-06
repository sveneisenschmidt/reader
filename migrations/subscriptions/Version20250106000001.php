<?php

declare(strict_types=1);

namespace DoctrineMigrations\Subscriptions;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250106000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create subscription table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE subscription (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            url VARCHAR(500) NOT NULL,
            name VARCHAR(255) NOT NULL,
            guid VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_URL ON subscription (user_id, url)');
        $this->addSql('CREATE INDEX IDX_SUBSCRIPTION_USER ON subscription (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscription');
    }
}
