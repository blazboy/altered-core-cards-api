<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260411000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create card_search flat table for optimised effect/keyword filtering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE card_search (
                card_id    INT PRIMARY KEY,
                t1 INT, c1 INT, e1 INT,
                t2 INT, c2 INT, e2 INT,
                t3 INT, c3 INT, e3 INT,
                has_effect BOOLEAN NOT NULL DEFAULT FALSE,
                keywords   TEXT[]  NOT NULL DEFAULT '{}'
            )
        ");

        // Composite slot indexes (trigger+condition+effect per slot)
        $this->addSql('CREATE INDEX idx_cs_slot1 ON card_search (t1, c1, e1)');
        $this->addSql('CREATE INDEX idx_cs_slot2 ON card_search (t2, c2, e2)');
        $this->addSql('CREATE INDEX idx_cs_slot3 ON card_search (t3, c3, e3)');

        // Trigger-only indexes (SameTriggerCount + TriggerType filters)
        $this->addSql('CREATE INDEX idx_cs_t1 ON card_search (t1) WHERE t1 IS NOT NULL');
        $this->addSql('CREATE INDEX idx_cs_t2 ON card_search (t2) WHERE t2 IS NOT NULL');
        $this->addSql('CREATE INDEX idx_cs_t3 ON card_search (t3) WHERE t3 IS NOT NULL');

        // GIN index for keywords array (@> / &&)
        $this->addSql('CREATE INDEX idx_cs_keywords ON card_search USING GIN (keywords)');

        $this->addSql('CREATE INDEX idx_cs_has_effect ON card_search (has_effect)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS card_search');
    }
}
