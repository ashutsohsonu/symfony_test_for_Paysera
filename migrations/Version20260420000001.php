<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: accounts + transfers tables.
 */
final class Version20260420000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts and transfers tables';
    }

    public function up(Schema $schema): void
    {
        // accounts table — balance stored as bigint minor units
        $this->addSql(<<<'SQL'
            CREATE TABLE accounts (
                id                  CHAR(36)            NOT NULL,
                owner_name          VARCHAR(255)        NOT NULL,
                active              TINYINT(1)          NOT NULL DEFAULT 1,
                balance_minor_units BIGINT UNSIGNED     NOT NULL DEFAULT 0,
                currency            CHAR(3)             NOT NULL,
                created_at          DATETIME            NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at          DATETIME            NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                INDEX idx_accounts_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // transfers table — immutable ledger
        $this->addSql(<<<'SQL'
            CREATE TABLE transfers (
                id                      CHAR(36)            NOT NULL,
                source_account_id       CHAR(36)            NOT NULL,
                destination_account_id  CHAR(36)            NOT NULL,
                amount_minor_units      BIGINT UNSIGNED     NOT NULL,
                currency                CHAR(3)             NOT NULL,
                status                  VARCHAR(20)         NOT NULL DEFAULT 'pending',
                failure_reason          TEXT                NULL,
                idempotency_key         VARCHAR(255)        NOT NULL,
                created_at              DATETIME            NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at              DATETIME            NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id),
                UNIQUE INDEX uniq_transfers_idempotency_key (idempotency_key),
                INDEX idx_transfers_source (source_account_id),
                INDEX idx_transfers_destination (destination_account_id),
                INDEX idx_transfers_status (status),
                CONSTRAINT fk_transfers_source
                    FOREIGN KEY (source_account_id) REFERENCES accounts(id),
                CONSTRAINT fk_transfers_destination
                    FOREIGN KEY (destination_account_id) REFERENCES accounts(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS transfers');
        $this->addSql('DROP TABLE IF EXISTS accounts');
    }
}
