<?php

declare(strict_types=1);

namespace DoctrineMigrations\Users;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250107000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add username field, delete existing users';
    }

    public function up(Schema $schema): void
    {
        // Delete all existing users (dummy users from old system)
        $this->addSql('DELETE FROM seen_status');
        $this->addSql('DELETE FROM read_status');
        $this->addSql('DELETE FROM user');

        // Add username column
        $this->addSql('ALTER TABLE user ADD COLUMN username VARCHAR(255)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USERNAME ON user (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USERNAME');
        $this->addSql('ALTER TABLE user DROP COLUMN username');
    }
}
