<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427CreateInvoiceTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoice table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS invoice (id SERIAL NOT NULL, created_at TIMESTAMP NOT NULL, amount NUMERIC(10,2) NOT NULL, client_id INT DEFAULT NULL, user_id INT DEFAULT NULL, PRIMARY KEY(id))');
        // Add FKs if referenced tables exist
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='client') THEN
    PERFORM 1;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='users') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_invoice_user') THEN
      EXECUTE 'ALTER TABLE invoice ADD CONSTRAINT fk_invoice_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL';
    END IF;
  END IF;
END$$;
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS invoice');
    }
}
