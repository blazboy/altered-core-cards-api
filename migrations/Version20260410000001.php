<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ability_trigger, ability_condition, ability_effect tables and link them to main_effect via FKs + abilityKey';
    }

    public function up(Schema $schema): void
    {
        // --- ability_trigger ---
        $this->addSql('CREATE TABLE IF NOT EXISTS ability_trigger (
            id          SERIAL NOT NULL,
            altered_id  INT NOT NULL,
            text_fr     TEXT DEFAULT NULL,
            text_en     TEXT DEFAULT NULL,
            text_it     TEXT DEFAULT NULL,
            text_es     TEXT DEFAULT NULL,
            text_de     TEXT DEFAULT NULL,
            is_support  BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_ability_trigger_altered_id ON ability_trigger (altered_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ability_trigger_altered_id ON ability_trigger (altered_id)');

        // --- ability_condition ---
        $this->addSql('CREATE TABLE IF NOT EXISTS ability_condition (
            id          SERIAL NOT NULL,
            altered_id  INT NOT NULL,
            text_fr     TEXT DEFAULT NULL,
            text_en     TEXT DEFAULT NULL,
            text_it     TEXT DEFAULT NULL,
            text_es     TEXT DEFAULT NULL,
            text_de     TEXT DEFAULT NULL,
            is_support  BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_ability_condition_altered_id ON ability_condition (altered_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ability_condition_altered_id ON ability_condition (altered_id)');

        // --- ability_effect ---
        $this->addSql('CREATE TABLE IF NOT EXISTS ability_effect (
            id          SERIAL NOT NULL,
            altered_id  INT NOT NULL,
            text_fr     TEXT DEFAULT NULL,
            text_en     TEXT DEFAULT NULL,
            text_it     TEXT DEFAULT NULL,
            text_es     TEXT DEFAULT NULL,
            text_de     TEXT DEFAULT NULL,
            is_support  BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_ability_effect_altered_id ON ability_effect (altered_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ability_effect_altered_id ON ability_effect (altered_id)');

        // --- main_effect: new columns (idempotent via IF NOT EXISTS / DO NOTHING) ---
        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name='main_effect' AND column_name='ability_key'
                ) THEN
                    ALTER TABLE main_effect ADD ability_key VARCHAR(30) DEFAULT NULL;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name='main_effect' AND column_name='ability_trigger_id'
                ) THEN
                    ALTER TABLE main_effect ADD ability_trigger_id INT DEFAULT NULL;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name='main_effect' AND column_name='ability_condition_id'
                ) THEN
                    ALTER TABLE main_effect ADD ability_condition_id INT DEFAULT NULL;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name='main_effect' AND column_name='ability_effect_id'
                ) THEN
                    ALTER TABLE main_effect ADD ability_effect_id INT DEFAULT NULL;
                END IF;
            END
            \$\$
        ");

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_main_effect_ability_key ON main_effect (ability_key)');

        $this->addSql("
            DO \$\$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_name='FK_main_effect_trigger'
                ) THEN
                    ALTER TABLE main_effect ADD CONSTRAINT FK_main_effect_trigger
                        FOREIGN KEY (ability_trigger_id) REFERENCES ability_trigger (id) ON DELETE SET NULL;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_name='FK_main_effect_condition'
                ) THEN
                    ALTER TABLE main_effect ADD CONSTRAINT FK_main_effect_condition
                        FOREIGN KEY (ability_condition_id) REFERENCES ability_condition (id) ON DELETE SET NULL;
                END IF;
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.table_constraints
                    WHERE constraint_name='FK_main_effect_effect'
                ) THEN
                    ALTER TABLE main_effect ADD CONSTRAINT FK_main_effect_effect
                        FOREIGN KEY (ability_effect_id) REFERENCES ability_effect (id) ON DELETE SET NULL;
                END IF;
            END
            \$\$
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_effect DROP CONSTRAINT IF EXISTS FK_main_effect_trigger');
        $this->addSql('ALTER TABLE main_effect DROP CONSTRAINT IF EXISTS FK_main_effect_condition');
        $this->addSql('ALTER TABLE main_effect DROP CONSTRAINT IF EXISTS FK_main_effect_effect');
        $this->addSql('DROP INDEX IF EXISTS idx_main_effect_ability_key');
        $this->addSql('ALTER TABLE main_effect DROP COLUMN IF EXISTS ability_key');
        $this->addSql('ALTER TABLE main_effect DROP COLUMN IF EXISTS ability_trigger_id');
        $this->addSql('ALTER TABLE main_effect DROP COLUMN IF EXISTS ability_condition_id');
        $this->addSql('ALTER TABLE main_effect DROP COLUMN IF EXISTS ability_effect_id');
        $this->addSql('DROP TABLE IF EXISTS ability_trigger');
        $this->addSql('DROP TABLE IF EXISTS ability_condition');
        $this->addSql('DROP TABLE IF EXISTS ability_effect');
    }
}
