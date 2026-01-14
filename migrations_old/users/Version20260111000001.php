<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260111000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change user_preference.is_enabled to value text field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_preference_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            preference_key VARCHAR(50) NOT NULL,
            value TEXT NOT NULL
        )');

        $this->addSql("INSERT INTO user_preference_new (id, user_id, preference_key, value)
            SELECT id, user_id, preference_key, CASE WHEN is_enabled THEN '1' ELSE '0' END
            FROM user_preference");

        $this->addSql('DROP TABLE user_preference');
        $this->addSql('ALTER TABLE user_preference_new RENAME TO user_preference');

        $this->addSql('CREATE UNIQUE INDEX user_preference_key ON user_preference (user_id, preference_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_preference_old (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            preference_key VARCHAR(50) NOT NULL,
            is_enabled BOOLEAN NOT NULL
        )');

        $this->addSql("INSERT INTO user_preference_old (id, user_id, preference_key, is_enabled)
            SELECT id, user_id, preference_key, CASE WHEN value = '1' THEN 1 ELSE 0 END
            FROM user_preference");

        $this->addSql('DROP TABLE user_preference');
        $this->addSql('ALTER TABLE user_preference_old RENAME TO user_preference');

        $this->addSql('CREATE UNIQUE INDEX user_preference_key ON user_preference (user_id, preference_key)');
    }
}
