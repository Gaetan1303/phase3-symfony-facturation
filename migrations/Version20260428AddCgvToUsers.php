<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428AddCgvToUsers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cgv (text) column to users table';
    }

    public function up(Schema $schema): void
    {
        // Add cgv column
        $this->addSql('ALTER TABLE users ADD cgv TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN cgv');
    }
}
