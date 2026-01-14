<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260110132609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_preference table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE user_preference (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, preference_key VARCHAR(50) NOT NULL, is_enabled BOOLEAN NOT NULL)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX user_preference_key ON user_preference (user_id, preference_key)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_preference');
    }
}
