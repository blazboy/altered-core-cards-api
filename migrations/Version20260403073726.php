<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260403073726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card_sub_type_link DROP CONSTRAINT fk_83d4d3664acc9a20');
        $this->addSql('ALTER TABLE card_sub_type_link DROP CONSTRAINT fk_83d4d3665d408ffd');
        $this->addSql('DROP TABLE card_sub_type_link');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d34448f8da');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d367922d0f');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d3752782e1');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d3cd9be584');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d3925606e5');
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d3fe9bd68b');
        $this->addSql('DROP INDEX idx_161498d34448f8da');
        $this->addSql('DROP INDEX idx_161498d367922d0f');
        $this->addSql('DROP INDEX idx_161498d3752782e1');
        $this->addSql('DROP INDEX idx_161498d3cd9be584');
        $this->addSql('DROP INDEX idx_161498d3925606e5');
        $this->addSql('DROP INDEX idx_161498d3fe9bd68b');
        $this->addSql('ALTER TABLE card DROP name');
        $this->addSql('ALTER TABLE card DROP elements');
        $this->addSql('ALTER TABLE card DROP card_product');
        $this->addSql('ALTER TABLE card DROP main_cost');
        $this->addSql('ALTER TABLE card DROP recall_cost');
        $this->addSql('ALTER TABLE card DROP ocean_power');
        $this->addSql('ALTER TABLE card DROP mountain_power');
        $this->addSql('ALTER TABLE card DROP forest_power');
        $this->addSql('ALTER TABLE card DROP echo_effect');
        $this->addSql('ALTER TABLE card DROP main_effect');
        $this->addSql('ALTER TABLE card DROP permanent');
        $this->addSql('ALTER TABLE card DROP faction_id');
        $this->addSql('ALTER TABLE card DROP effect1_id');
        $this->addSql('ALTER TABLE card DROP effect2_id');
        $this->addSql('ALTER TABLE card DROP effect3_id');
        $this->addSql('ALTER TABLE card DROP card_type_id');
        $this->addSql('ALTER TABLE card DROP is_banned');
        $this->addSql('ALTER TABLE card DROP is_errated');
        $this->addSql('ALTER TABLE card DROP is_suspended');
        $this->addSql('ALTER TABLE card DROP card_history_status_id');
        $this->addSql('ALTER TABLE card_ruling DROP CONSTRAINT fk_aa7b3b644acc9a20');
        $this->addSql('DROP INDEX idx_aa7b3b644acc9a20');
        $this->addSql('ALTER TABLE card_ruling DROP card_id');
        $this->addSql('ALTER TABLE card_ruling ALTER card_group_id SET NOT NULL');
        $this->addSql('ALTER TABLE lore_entry DROP CONSTRAINT fk_8cabe71d4acc9a20');
        $this->addSql('DROP INDEX idx_8cabe71d4acc9a20');
        $this->addSql('ALTER TABLE lore_entry DROP card_id');
        $this->addSql('ALTER TABLE lore_entry ALTER card_group_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE card_sub_type_link (card_id INT NOT NULL, card_sub_type_id INT NOT NULL, PRIMARY KEY (card_id, card_sub_type_id))');
        $this->addSql('CREATE INDEX idx_83d4d3664acc9a20 ON card_sub_type_link (card_id)');
        $this->addSql('CREATE INDEX idx_83d4d3665d408ffd ON card_sub_type_link (card_sub_type_id)');
        $this->addSql('ALTER TABLE card_sub_type_link ADD CONSTRAINT fk_83d4d3664acc9a20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card_sub_type_link ADD CONSTRAINT fk_83d4d3665d408ffd FOREIGN KEY (card_sub_type_id) REFERENCES card_sub_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card ADD name VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE card ADD elements JSON NOT NULL');
        $this->addSql('ALTER TABLE card ADD card_product JSON NOT NULL');
        $this->addSql('ALTER TABLE card ADD main_cost INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD recall_cost INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD ocean_power INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD mountain_power INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD forest_power INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD echo_effect TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD main_effect TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD permanent VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD faction_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD effect1_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD effect2_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD effect3_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD card_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD is_banned BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE card ADD is_errated BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE card ADD is_suspended BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE card ADD card_history_status_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d34448f8da FOREIGN KEY (faction_id) REFERENCES faction (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d367922d0f FOREIGN KEY (effect1_id) REFERENCES main_effect (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d3752782e1 FOREIGN KEY (effect2_id) REFERENCES main_effect (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d3cd9be584 FOREIGN KEY (effect3_id) REFERENCES main_effect (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d3925606e5 FOREIGN KEY (card_type_id) REFERENCES card_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d3fe9bd68b FOREIGN KEY (card_history_status_id) REFERENCES card_history_status (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_161498d34448f8da ON card (faction_id)');
        $this->addSql('CREATE INDEX idx_161498d367922d0f ON card (effect1_id)');
        $this->addSql('CREATE INDEX idx_161498d3752782e1 ON card (effect2_id)');
        $this->addSql('CREATE INDEX idx_161498d3cd9be584 ON card (effect3_id)');
        $this->addSql('CREATE INDEX idx_161498d3925606e5 ON card (card_type_id)');
        $this->addSql('CREATE INDEX idx_161498d3fe9bd68b ON card (card_history_status_id)');
        $this->addSql('ALTER TABLE card_ruling ADD card_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card_ruling ALTER card_group_id DROP NOT NULL');
        $this->addSql('ALTER TABLE card_ruling ADD CONSTRAINT fk_aa7b3b644acc9a20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_aa7b3b644acc9a20 ON card_ruling (card_id)');
        $this->addSql('ALTER TABLE lore_entry ADD card_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE lore_entry ALTER card_group_id DROP NOT NULL');
        $this->addSql('ALTER TABLE lore_entry ADD CONSTRAINT fk_8cabe71d4acc9a20 FOREIGN KEY (card_id) REFERENCES card (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_8cabe71d4acc9a20 ON lore_entry (card_id)');
    }
}
