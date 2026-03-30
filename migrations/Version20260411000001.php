<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop trigger_type and condition_fr columns from main_effect (replaced by ability_trigger/condition FKs)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_effect DROP COLUMN IF EXISTS trigger_type');
        $this->addSql('ALTER TABLE main_effect DROP COLUMN IF EXISTS condition_fr');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_effect ADD COLUMN IF NOT EXISTS trigger_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE main_effect ADD COLUMN IF NOT EXISTS condition_fr TEXT DEFAULT NULL');
    }
}
