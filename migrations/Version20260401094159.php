<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260401094159 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ALTER is_banned DROP DEFAULT');
        $this->addSql('ALTER TABLE card ALTER is_errated DROP DEFAULT');
        $this->addSql('ALTER TABLE card ALTER is_suspended DROP DEFAULT');
        $this->addSql('ALTER TABLE card ALTER is_ownerless DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ALTER is_banned SET DEFAULT false');
        $this->addSql('ALTER TABLE card ALTER is_errated SET DEFAULT false');
        $this->addSql('ALTER TABLE card ALTER is_suspended SET DEFAULT false');
        $this->addSql('ALTER TABLE card ALTER is_ownerless SET DEFAULT false');
    }
}
