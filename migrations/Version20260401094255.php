<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401094255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ADD is_parent_serialized BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE card RENAME COLUMN is_ownerless TO is_serialized');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ADD is_ownerless BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE card DROP is_serialized');
        $this->addSql('ALTER TABLE card DROP is_parent_serialized');
    }
}
