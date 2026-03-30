<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404133806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_group ADD rarity_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_group ADD CONSTRAINT FK_55F4B503F3747573 FOREIGN KEY (rarity_id) REFERENCES rarity (id)');
        $this->addSql('CREATE INDEX IDX_55F4B503F3747573 ON card_group (rarity_id)');
        $this->addSql('
            UPDATE card_group cg
            SET rarity_id = c.rarity_id
            FROM (
                SELECT DISTINCT ON (card_group_id) card_group_id, rarity_id
                FROM card
                WHERE rarity_id IS NOT NULL
            ) c
            WHERE cg.id = c.card_group_id
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_group DROP CONSTRAINT FK_55F4B503F3747573');
        $this->addSql('DROP INDEX IDX_55F4B503F3747573');
        $this->addSql('ALTER TABLE card_group DROP rarity_id');
    }
}
