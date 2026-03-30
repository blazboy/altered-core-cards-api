<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401160215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_8cabe71dac10a1214180c698');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8CABE71D4ACC9A20AC10A1214180C698 ON lore_entry (card_id, altered_id, locale)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_8CABE71D4ACC9A20AC10A1214180C698');
        $this->addSql('CREATE UNIQUE INDEX uniq_8cabe71dac10a1214180c698 ON lore_entry (altered_id, locale)');
    }
}
