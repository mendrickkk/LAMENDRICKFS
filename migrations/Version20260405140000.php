<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops users.phone if it was added by an older revision of Version20260404190000.
 */
final class Version20260405140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove users.phone column when present';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform) {
            return;
        }

        $n = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone'"
        );
        if ($n > 0) {
            $this->addSql('ALTER TABLE users DROP phone');
        }
    }

    public function down(Schema $schema): void
    {
        // Intentionally empty: phone field is not used in the app.
    }
}
