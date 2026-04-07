<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align verification_token with is_verified: verified users have no token;
 * unverified users get a non-null token (backfill legacy NULL rows).
 */
final class Version20260403120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear verification_token for verified users; backfill token for unverified users missing one';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE users SET verification_token = NULL WHERE is_verified = 1');
        $this->addSql(
            'UPDATE users SET verification_token = LOWER(HEX(RANDOM_BYTES(32))) WHERE is_verified = 0 AND (verification_token IS NULL OR verification_token = \'\')'
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
