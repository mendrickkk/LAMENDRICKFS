<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add orders.client_id for client account order history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_orders_client FOREIGN KEY (client_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_orders_client');
        $this->addSql('ALTER TABLE orders DROP client_id');
    }
}
