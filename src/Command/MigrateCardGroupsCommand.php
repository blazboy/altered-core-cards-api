<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One-shot data migration: populates card_group + card_group_translation
 * from existing card data and links every card to its group.
 *
 * Grouping key:
 *   C / R  →  faction + number + rarity          slug = OR-017-C
 *   U      →  faction + number + rarity + variant slug = OR-017-U-185
 *
 * Reference format: ALT_CORE_B_OR_17_U_185
 *   SPLIT_PART(ref, '_', 4) = faction (OR)
 *   SPLIT_PART(ref, '_', 5) = card number (17)
 *   SPLIT_PART(ref, '_', 6) = rarity (C | R | U)
 *   SPLIT_PART(ref, '_', 7) = variant number for Unique (185), empty otherwise
 *
 * Deck limit: U → 1 copy, everything else → 3 copies.
 *
 * Run AFTER doctrine:migrations:migrate (schema must exist).
 * Safe to re-run — clears all groups first, then rebuilds from scratch.
 */
#[AsCommand(
    name: 'app:migrate:card-groups',
    description: 'Migrate gameplay data from Card into CardGroup (one-shot)',
)]
class MigrateCardGroupsCommand extends Command
{
    /**
     * SQL expression that computes the canonical slug from a card reference.
     *
     * C/R: OR-017-C
     * U:   OR-017-U-185
     */
    private const SLUG_EXPR = "
        CASE SPLIT_PART(reference, '_', 6)
            WHEN 'U' THEN CONCAT(
                SPLIT_PART(reference, '_', 4), '-',
                LPAD(SPLIT_PART(reference, '_', 5), 3, '0'), '-',
                'U', '-',
                SPLIT_PART(reference, '_', 7)
            )
            ELSE CONCAT(
                SPLIT_PART(reference, '_', 4), '-',
                LPAD(SPLIT_PART(reference, '_', 5), 3, '0'), '-',
                SPLIT_PART(reference, '_', 6)
            )
        END
    ";

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->connection->beginTransaction();

        try {
            // ── 0. Clean up previous run ──────────────────────────────────────
            $io->text('Cleaning up previous migration...');
            $this->connection->executeStatement('UPDATE card SET card_group_id = NULL');
            $this->connection->executeStatement('UPDATE card_ruling SET card_group_id = NULL');
            $this->connection->executeStatement('UPDATE lore_entry SET card_group_id = NULL');
            $this->connection->executeStatement('DELETE FROM card_group_sub_type_link');
            $this->connection->executeStatement('DELETE FROM card_group_translation');
            $this->connection->executeStatement('DELETE FROM card_group');

            // ── 1. Create one CardGroup per unique slug ────────────────────────
            // CTE computes the slug so DISTINCT ON can group on it directly.
            $io->text('Creating card groups...');
            $slugExpr = self::SLUG_EXPR;
            $inserted = $this->connection->executeStatement("
                WITH cards_with_slug AS (
                    SELECT *,
                        ($slugExpr) AS computed_slug
                    FROM card
                    WHERE faction_id IS NOT NULL
                      AND SPLIT_PART(reference, '_', 4) <> ''
                )
                INSERT INTO card_group (
                    slug, faction_id, card_type_id, card_history_status_id,
                    effect1_id, effect2_id, effect3_id,
                    main_cost, recall_cost,
                    ocean_power, mountain_power, forest_power,
                    echo_effect, permanent,
                    is_banned, is_errated, is_suspended,
                    deck_limit, creation_date
                )
                SELECT DISTINCT ON (computed_slug)
                    computed_slug                                            AS slug,
                    faction_id,
                    card_type_id,
                    card_history_status_id,
                    effect1_id,
                    effect2_id,
                    effect3_id,
                    main_cost,
                    recall_cost,
                    ocean_power,
                    mountain_power,
                    forest_power,
                    echo_effect,
                    permanent,
                    is_banned,
                    is_errated,
                    is_suspended,
                    CASE SPLIT_PART(reference, '_', 6) WHEN 'U' THEN 1 ELSE 3 END AS deck_limit,
                    NOW()
                FROM cards_with_slug
                ORDER BY computed_slug, id ASC
                ON CONFLICT (slug) DO NOTHING
            ");
            $io->text(sprintf('  %d card groups created.', $inserted));

            // ── 2. Migrate translations (name + main_effect + echo_effect) ─────
            $io->text('Migrating translations...');
            $this->connection->executeStatement("
                WITH cards_with_slug AS (
                    SELECT c.id AS card_id, ($slugExpr) AS computed_slug
                    FROM card c
                    WHERE faction_id IS NOT NULL
                      AND SPLIT_PART(reference, '_', 4) <> ''
                )
                INSERT INTO card_group_translation (card_group_id, locale, name, main_effect, echo_effect, creation_date)
                SELECT DISTINCT ON (cg.id, ct.locale)
                    cg.id,
                    ct.locale,
                    ct.name,
                    ct.main_effect,
                    ct.echo_effect,
                    NOW()
                FROM card_group cg
                JOIN cards_with_slug cws ON cg.slug = cws.computed_slug
                JOIN card_translation ct ON ct.card_id = cws.card_id
                ORDER BY cg.id, ct.locale, cws.card_id ASC
                ON CONFLICT (card_group_id, locale) DO NOTHING
            ");

            // ── 3. Migrate sub-type links ─────────────────────────────────────
            $io->text('Migrating sub-type links...');
            $this->connection->executeStatement("
                WITH cards_with_slug AS (
                    SELECT c.id AS card_id, ($slugExpr) AS computed_slug
                    FROM card c
                    WHERE faction_id IS NOT NULL
                      AND SPLIT_PART(reference, '_', 4) <> ''
                )
                INSERT INTO card_group_sub_type_link (card_group_id, card_sub_type_id)
                SELECT DISTINCT cg.id, cstl.card_sub_type_id
                FROM card_group cg
                JOIN cards_with_slug cws ON cg.slug = cws.computed_slug
                JOIN card_sub_type_link cstl ON cstl.card_id = cws.card_id
                ON CONFLICT DO NOTHING
            ");

            // ── 4. Link every card to its group ───────────────────────────────
            $io->text('Linking cards to groups...');
            $linked = $this->connection->executeStatement("
                UPDATE card c
                SET card_group_id = cg.id
                FROM card_group cg
                WHERE cg.slug = ($slugExpr)
                  AND c.card_group_id IS NULL
            ");
            $io->text(sprintf('  %d cards linked.', $linked));

            // ── 5. Set variation from existing flags ──────────────────────────
            $io->text('Setting variation flags...');
            $this->connection->executeStatement("UPDATE card SET variation = 'standard'");
            $this->connection->executeStatement("UPDATE card SET variation = 'kickstarter' WHERE kickstarter = TRUE");
            $this->connection->executeStatement("UPDATE card SET variation = 'promo' WHERE promo = TRUE");
            $this->connection->executeStatement("UPDATE card SET variation = 'serialized' WHERE is_serialized = TRUE");

            // ── 6. Migrate rulings to cardGroup ──────────────────────────────
            $io->text('Migrating card rulings...');
            $this->connection->executeStatement("
                UPDATE card_ruling cr
                SET card_group_id = c.card_group_id
                FROM card c
                WHERE cr.card_id = c.id
                  AND c.card_group_id IS NOT NULL
                  AND cr.card_group_id IS NULL
            ");

            // ── 7. Migrate lore entries (canonical card per group only) ───────
            $io->text('Migrating lore entries...');
            $this->connection->executeStatement("
                WITH canonical_cards AS (
                    SELECT DISTINCT ON (($slugExpr)) id
                    FROM card
                    WHERE faction_id IS NOT NULL
                      AND SPLIT_PART(reference, '_', 4) <> ''
                    ORDER BY ($slugExpr), id ASC
                )
                UPDATE lore_entry le
                SET card_group_id = c.card_group_id
                FROM card c
                WHERE le.card_id = c.id
                  AND c.card_group_id IS NOT NULL
                  AND le.card_group_id IS NULL
                  AND c.id IN (SELECT id FROM canonical_cards)
            ");

            $deleted = $this->connection->executeStatement(
                'DELETE FROM lore_entry WHERE card_group_id IS NULL AND card_id IS NOT NULL'
            );
            if ($deleted > 0) {
                $io->text(sprintf('  Removed %d duplicate lore entries from non-canonical cards.', $deleted));
            }

            $this->connection->commit();

            $unlinked = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM card WHERE card_group_id IS NULL');
            if ($unlinked > 0) {
                $io->warning(sprintf(
                    '%d cards have no card_group (no faction or unparseable reference — tokens/neutrals).',
                    $unlinked
                ));
            }

            $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM card_group');
            $io->success(sprintf(
                'Migration complete — %d card groups, %d cards linked, %d unlinked.',
                $total, $linked, $unlinked
            ));

        } catch (\Throwable $e) {
            $this->connection->rollBack();
            $io->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
