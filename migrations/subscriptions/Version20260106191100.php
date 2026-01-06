<?php

declare(strict_types=1);

namespace DoctrineMigrations\Subscriptions;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260106191100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add folder column to subscription table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD COLUMN folder JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP COLUMN folder');
    }
}
