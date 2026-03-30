<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Delete incomplete duplicate effects: when two effects share the same text_fr
 * but one has all translations set and the other has EN/DE/ES/IT null, keep the
 * complete one and remove the incomplete one (which would violate the unique
 * constraint as soon as the importer fills in its missing translations).
 *
 * Also reassigns any card_group references pointing to the deleted effects.
 */
final class Version20260408000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purge incomplete duplicate main_effect rows that share a text_fr with a fully-translated effect';
    }

    public function up(Schema $schema): void
    {
        // Step 1: reassign card_group FK references from incomplete → complete effect
        $this->addSql("
            UPDATE card_group
            SET effect1_id = canonical.id
            FROM (
                SELECT incomplete.id AS old_id, complete.id AS id
                FROM main_effect incomplete
                JOIN main_effect complete
                  ON complete.text_fr = incomplete.text_fr
                 AND complete.text_en IS NOT NULL
                 AND complete.id <> incomplete.id
                WHERE incomplete.text_en IS NULL
            ) canonical
            WHERE card_group.effect1_id = canonical.old_id
        ");

        $this->addSql("
            UPDATE card_group
            SET effect2_id = canonical.id
            FROM (
                SELECT incomplete.id AS old_id, complete.id AS id
                FROM main_effect incomplete
                JOIN main_effect complete
                  ON complete.text_fr = incomplete.text_fr
                 AND complete.text_en IS NOT NULL
                 AND complete.id <> incomplete.id
                WHERE incomplete.text_en IS NULL
            ) canonical
            WHERE card_group.effect2_id = canonical.old_id
        ");

        $this->addSql("
            UPDATE card_group
            SET effect3_id = canonical.id
            FROM (
                SELECT incomplete.id AS old_id, complete.id AS id
                FROM main_effect incomplete
                JOIN main_effect complete
                  ON complete.text_fr = incomplete.text_fr
                 AND complete.text_en IS NOT NULL
                 AND complete.id <> incomplete.id
                WHERE incomplete.text_en IS NULL
            ) canonical
            WHERE card_group.effect3_id = canonical.old_id
        ");

        // Step 2: delete the incomplete duplicates
        $this->addSql("
            DELETE FROM main_effect incomplete
            USING main_effect complete
            WHERE complete.text_fr = incomplete.text_fr
              AND complete.text_en IS NOT NULL
              AND complete.id <> incomplete.id
              AND incomplete.text_en IS NULL
        ");
    }

    public function down(Schema $schema): void
    {
        // Irreversible — deleted rows cannot be restored
        $this->addSql('SELECT 1');
    }
}
