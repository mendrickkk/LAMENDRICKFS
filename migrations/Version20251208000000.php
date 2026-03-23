<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add user management fields to users table
 */
final class Version20251208000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isActive, createdAt, updatedAt, and createdBy fields to users table';
    }

    public function up(Schema $schema): void
    {
        // Add is_active column
        $this->addSql('ALTER TABLE users ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        
        // Add created_at column
        $this->addSql('ALTER TABLE users ADD created_at DATETIME DEFAULT NULL');
        
        // Add updated_at column
        $this->addSql('ALTER TABLE users ADD updated_at DATETIME DEFAULT NULL');
        
        // Add created_by_id column
        $this->addSql('ALTER TABLE users ADD created_by_id INT DEFAULT NULL');
        
        // Add foreign key constraint
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        
        // Add index for foreign key
        $this->addSql('CREATE INDEX IDX_1483A5E9B03A8386 ON users (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key and index
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9B03A8386');
        $this->addSql('DROP INDEX IDX_1483A5E9B03A8386 ON users');
        
        // Drop columns
        $this->addSql('ALTER TABLE users DROP is_active');
        $this->addSql('ALTER TABLE users DROP created_at');
        $this->addSql('ALTER TABLE users DROP updated_at');
        $this->addSql('ALTER TABLE users DROP created_by_id');
    }
}

