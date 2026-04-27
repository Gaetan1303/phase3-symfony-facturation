<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427AlignBusinessSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align business schema with clients, products, invoice lines and invoice metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS client (id SERIAL NOT NULL, user_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, siret VARCHAR(20) DEFAULT NULL, rib VARCHAR(34) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_client_user ON client (user_id)');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_client_user') THEN
    EXECUTE 'ALTER TABLE client ADD CONSTRAINT fk_client_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE';
  END IF;
END$$;
SQL
        );

        $this->addSql("CREATE TABLE IF NOT EXISTS product (id SERIAL NOT NULL, user_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, price NUMERIC(10,2) NOT NULL, unit VARCHAR(32) NOT NULL DEFAULT 'piece', PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_user ON product (user_id)');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_product_user') THEN
    EXECUTE 'ALTER TABLE product ADD CONSTRAINT fk_product_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE';
  END IF;
END$$;
SQL
        );

        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'invoice' AND column_name = 'amount'
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'invoice' AND column_name = 'total_ttc'
  ) THEN
    EXECUTE 'ALTER TABLE invoice RENAME COLUMN amount TO total_ttc';
  END IF;
END$$;
SQL
        );

        $this->addSql('ALTER TABLE invoice ADD COLUMN IF NOT EXISTS number VARCHAR(32)');
        $this->addSql("ALTER TABLE invoice ADD COLUMN IF NOT EXISTS status VARCHAR(32) NOT NULL DEFAULT 'draft'");
        $this->addSql('ALTER TABLE invoice ADD COLUMN IF NOT EXISTS total_ttc NUMERIC(10,2) NOT NULL DEFAULT 0');
        $this->addSql("UPDATE invoice SET number = CONCAT('FACT-', TO_CHAR(created_at, 'YYYYMMDD'), '-', id) WHERE number IS NULL");
        $this->addSql('ALTER TABLE invoice ALTER COLUMN number SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_invoice_number ON invoice (number)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_invoice_client ON invoice (client_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_invoice_user ON invoice (user_id)');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_invoice_client') THEN
    EXECUTE 'ALTER TABLE invoice ADD CONSTRAINT fk_invoice_client FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE SET NULL';
  END IF;
END$$;
SQL
        );

        $this->addSql("CREATE TABLE IF NOT EXISTS invoice_line (id SERIAL NOT NULL, invoice_id INT NOT NULL, product_id INT DEFAULT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, unit VARCHAR(32) NOT NULL DEFAULT 'piece', quantity INT NOT NULL, unit_price NUMERIC(10,2) NOT NULL, position INT NOT NULL DEFAULT 0, PRIMARY KEY(id))");
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_invoice_line_invoice ON invoice_line (invoice_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_invoice_line_product ON invoice_line (product_id)');
        $this->addSql(<<<'SQL'
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_invoice_line_invoice') THEN
    EXECUTE 'ALTER TABLE invoice_line ADD CONSTRAINT fk_invoice_line_invoice FOREIGN KEY (invoice_id) REFERENCES invoice(id) ON DELETE CASCADE';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_invoice_line_product') THEN
    EXECUTE 'ALTER TABLE invoice_line ADD CONSTRAINT fk_invoice_line_product FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE SET NULL';
  END IF;
END$$;
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS invoice_line');
        $this->addSql('DROP TABLE IF EXISTS product');
        $this->addSql('DROP TABLE IF EXISTS client');
        $this->addSql('DROP INDEX IF EXISTS uniq_invoice_number');
        $this->addSql('ALTER TABLE invoice DROP COLUMN IF EXISTS number');
        $this->addSql('ALTER TABLE invoice DROP COLUMN IF EXISTS status');
        $this->addSql("DO $$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'invoice' AND column_name = 'total_ttc') THEN EXECUTE 'ALTER TABLE invoice RENAME COLUMN total_ttc TO amount'; END IF; END$$;");
    }
}