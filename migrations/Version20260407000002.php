<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add trigger_type and keywords (JSONB) to main_effect';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_effect ADD trigger_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE main_effect ADD keywords JSONB DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_main_effect_trigger_type ON main_effect (trigger_type)');
        $this->addSql('CREATE INDEX idx_main_effect_keywords ON main_effect USING GIN (keywords)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_main_effect_trigger_type');
        $this->addSql('DROP INDEX idx_main_effect_keywords');
        $this->addSql('ALTER TABLE main_effect DROP trigger_type');
        $this->addSql('ALTER TABLE main_effect DROP keywords');
    }
}
