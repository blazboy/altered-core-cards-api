<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401154612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_ruling ADD question TEXT NOT NULL');
        $this->addSql('ALTER TABLE card_ruling ADD answer TEXT NOT NULL');
        $this->addSql('ALTER TABLE card_ruling ADD event_format VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE card_ruling ADD ruling_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE card_ruling DROP locale');
        $this->addSql('ALTER TABLE card_ruling DROP content');
        $this->addSql('ALTER TABLE lore_entry ADD altered_id VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE lore_entry ADD type VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE lore_entry RENAME COLUMN content TO elements');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8CABE71DAC10A1214180C698 ON lore_entry (altered_id, locale)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_ruling ADD locale VARCHAR(8) NOT NULL');
        $this->addSql('ALTER TABLE card_ruling ADD content JSON NOT NULL');
        $this->addSql('ALTER TABLE card_ruling DROP question');
        $this->addSql('ALTER TABLE card_ruling DROP answer');
        $this->addSql('ALTER TABLE card_ruling DROP event_format');
        $this->addSql('ALTER TABLE card_ruling DROP ruling_date');
        $this->addSql('DROP INDEX UNIQ_8CABE71DAC10A1214180C698');
        $this->addSql('ALTER TABLE lore_entry DROP altered_id');
        $this->addSql('ALTER TABLE lore_entry DROP type');
        $this->addSql('ALTER TABLE lore_entry RENAME COLUMN elements TO content');
    }
}
