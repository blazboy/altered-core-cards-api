<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create artist table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE artist (
            id            SERIAL PRIMARY KEY,
            reference     VARCHAR(150) NOT NULL,
            creation_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            update_date   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            CONSTRAINT uniq_artist_reference UNIQUE (reference)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS artist');
    }
}
