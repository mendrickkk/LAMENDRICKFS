<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order_line table; add delivery_address and notes to orders; widen order_number to 30 chars';
    }

    public function up(Schema $schema): void
    {
        // Widen order_number for ORD-YYYYMMDD-##### format (18 chars, room to grow)
        $this->addSql('ALTER TABLE orders MODIFY order_number VARCHAR(30) NOT NULL');

        // Add delivery address and notes to orders
        $this->addSql('ALTER TABLE orders ADD delivery_address LONGTEXT DEFAULT NULL, ADD notes LONGTEXT DEFAULT NULL');

        // Create order_line table
        $this->addSql('CREATE TABLE order_line (
            id          INT AUTO_INCREMENT NOT NULL,
            order_id    INT NOT NULL,
            product_id  INT NOT NULL,
            quantity    INT NOT NULL,
            unit_price  DOUBLE PRECISION NOT NULL,
            INDEX IDX_order_line_order   (order_id),
            INDEX IDX_order_line_product (product_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_order_line_order   FOREIGN KEY (order_id)   REFERENCES orders  (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_line ADD CONSTRAINT FK_order_line_product FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_order_line_order');
        $this->addSql('ALTER TABLE order_line DROP FOREIGN KEY FK_order_line_product');
        $this->addSql('DROP TABLE order_line');

        $this->addSql('ALTER TABLE orders DROP delivery_address, DROP notes');
        $this->addSql('ALTER TABLE orders MODIFY order_number VARCHAR(20) NOT NULL');
    }
}
