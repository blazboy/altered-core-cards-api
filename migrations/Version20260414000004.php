<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create card_artist join table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_artist (
            card_id   INT  NOT NULL,
            artist_id INT  NOT NULL,
            PRIMARY KEY (card_id, artist_id),
            CONSTRAINT fk_card_artist_card   FOREIGN KEY (card_id)   REFERENCES card   (id) ON DELETE CASCADE,
            CONSTRAINT fk_card_artist_artist FOREIGN KEY (artist_id) REFERENCES artist (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_card_artist_card   ON card_artist (card_id)');
        $this->addSql('CREATE INDEX idx_card_artist_artist ON card_artist (artist_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS card_artist');
    }
}
