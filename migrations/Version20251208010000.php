<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create activity_log table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, user_relation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, username VARCHAR(255) NOT NULL, role VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, target_entity VARCHAR(100) DEFAULT NULL, target_id INT DEFAULT NULL, target_data LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_FD06F6479B4D58CE (user_relation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_FD06F6479B4D58CE FOREIGN KEY (user_relation_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_FD06F6479B4D58CE');
        $this->addSql('DROP TABLE activity_log');
    }
}

