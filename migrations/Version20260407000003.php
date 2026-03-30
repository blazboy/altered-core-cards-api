<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add condition_fr column to main_effect';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_effect ADD condition_fr TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_main_effect_condition ON main_effect USING GIN (to_tsvector(\'french\', COALESCE(condition_fr, \'\')))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_main_effect_condition');
        $this->addSql('ALTER TABLE main_effect DROP condition_fr');
    }
}
