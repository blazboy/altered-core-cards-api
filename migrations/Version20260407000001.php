<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deduplicate main_effect rows, delete orphans, add unique constraint';
    }

    public function up(Schema $schema): void
    {
        // Step 1: Build a mapping of duplicate IDs → canonical ID.
        // Canonical = the row with the most card_group references; ties broken by lowest ID.
        $this->addSql("
            CREATE TEMP TABLE effect_dedup AS
            WITH ref_counts AS (
                SELECT
                    e.id,
                    (
                        SELECT COUNT(*)
                        FROM card_group cg
                        WHERE cg.effect1_id = e.id
                           OR cg.effect2_id = e.id
                           OR cg.effect3_id = e.id
                    ) AS ref_count
                FROM main_effect e
            ),
            canonical AS (
                SELECT
                    e.id,
                    FIRST_VALUE(e.id) OVER (
                        PARTITION BY
                            COALESCE(e.text_fr, ''),
                            COALESCE(e.text_en, ''),
                            COALESCE(e.text_de, ''),
                            COALESCE(e.text_es, ''),
                            COALESCE(e.text_it, '')
                        ORDER BY rc.ref_count DESC, e.id ASC
                    ) AS canonical_id
                FROM main_effect e
                JOIN ref_counts rc ON rc.id = e.id
            )
            SELECT id, canonical_id
            FROM canonical
            WHERE id <> canonical_id
        ");

        // Step 2: Redirect card_group FK references to the canonical row.
        $this->addSql('
            UPDATE card_group
            SET effect1_id = ed.canonical_id
            FROM effect_dedup ed
            WHERE card_group.effect1_id = ed.id
        ');

        $this->addSql('
            UPDATE card_group
            SET effect2_id = ed.canonical_id
            FROM effect_dedup ed
            WHERE card_group.effect2_id = ed.id
        ');

        $this->addSql('
            UPDATE card_group
            SET effect3_id = ed.canonical_id
            FROM effect_dedup ed
            WHERE card_group.effect3_id = ed.id
        ');

        // Step 3: Delete non-canonical duplicate rows.
        $this->addSql('
            DELETE FROM main_effect
            WHERE id IN (SELECT id FROM effect_dedup)
        ');

        $this->addSql('DROP TABLE effect_dedup');

        // Step 4: Delete orphaned effects (not referenced by any card_group).
        $this->addSql('
            DELETE FROM main_effect
            WHERE NOT EXISTS (
                SELECT 1 FROM card_group cg
                WHERE cg.effect1_id = main_effect.id
                   OR cg.effect2_id = main_effect.id
                   OR cg.effect3_id = main_effect.id
            )
        ');

        // Step 5: Add unique index on the combination of all text columns.
        // COALESCE maps NULL to empty string so that two rows with identical
        // NULLs are correctly treated as duplicates.
        $this->addSql("
            CREATE UNIQUE INDEX uniq_main_effect_texts ON main_effect (
                COALESCE(text_fr, ''),
                COALESCE(text_en, ''),
                COALESCE(text_de, ''),
                COALESCE(text_es, ''),
                COALESCE(text_it, '')
            )
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_main_effect_texts');
        // Deleted rows cannot be restored automatically.
        // Re-run the import commands to repopulate main_effect if needed.
    }
}
