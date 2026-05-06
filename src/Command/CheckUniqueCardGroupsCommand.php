<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check:unique-card-groups',
    description: 'Show UNIQUE card_groups that have more than one card, grouped by set pair',
)]
class CheckUniqueCardGroupsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('min-cards', null, InputOption::VALUE_OPTIONAL, 'Minimum number of cards per card_group to report', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $minCards = (int) $input->getOption('min-cards');

        $io->title('UNIQUE card_groups with multiple cards');

        // ── 1. Distribution: how many cards per card_group? ─────────────────
        $io->section('Distribution (cards per card_group)');

        $dist = $this->connection->fetchAllAssociative(
            "SELECT nb_cards, COUNT(*) AS nb_groups
             FROM (
                 SELECT cg.id, COUNT(c.id) AS nb_cards
                 FROM card_group cg
                 JOIN card c    ON c.card_group_id = cg.id
                 JOIN rarity r  ON r.id = cg.rarity_id
                 WHERE r.reference = 'UNIQUE'
                 GROUP BY cg.id
             ) sub
             GROUP BY nb_cards
             ORDER BY nb_cards"
        );

        $io->table(['Cards per group', 'Nb card_groups'], array_map(
            fn($r) => [$r['nb_cards'], number_format((int) $r['nb_groups'])],
            $dist,
        ));

        // ── 2. By set pair: which sets share the same UNIQUE card_group? ─────
        $io->section('Set pairs sharing UNIQUE card_groups');

        $pairs = $this->connection->fetchAllAssociative(
            "SELECT
                 (SELECT STRING_AGG(s.reference, ',' ORDER BY s.reference)
                  FROM card_set s
                  WHERE s.id IN (SELECT DISTINCT c2.set_id FROM card c2 WHERE c2.card_group_id = cg.id)
                 ) AS sets,
                 COUNT(DISTINCT c.id) AS nb_cards
             FROM card_group cg
             JOIN card c    ON c.card_group_id = cg.id
             JOIN rarity r  ON r.id = cg.rarity_id
             WHERE r.reference = 'UNIQUE'
             GROUP BY cg.id
             HAVING COUNT(DISTINCT c.id) >= :min",
            ['min' => $minCards],
        );

        // Re-aggregate by set combination in PHP
        $bySets = [];
        foreach ($pairs as $row) {
            $key = $row['sets'];
            if (!isset($bySets[$key])) {
                $bySets[$key] = ['sets' => $key, 'nb_groups' => 0, 'nb_cards' => 0];
            }
            $bySets[$key]['nb_groups']++;
            $bySets[$key]['nb_cards'] += (int) $row['nb_cards'];
        }

        usort($bySets, fn($a, $b) => $b['nb_groups'] <=> $a['nb_groups']);

        if (empty($bySets)) {
            $io->success('No UNIQUE card_groups with multiple cards found.');
            return Command::SUCCESS;
        }

        $io->table(
            ['Sets', 'Shared card_groups', 'Total cards'],
            array_map(fn($r) => [
                $r['sets'],
                number_format($r['nb_groups']),
                number_format($r['nb_cards']),
            ], $bySets),
        );

        // ── 3. Sample: show a few offending card_groups ──────────────────────
        $io->section(sprintf('Sample card_groups with >= %d cards', $minCards));

        $samples = $this->connection->fetchAllAssociative(
            "SELECT
                 cg.slug,
                 STRING_AGG(c.reference, ', ' ORDER BY c.reference) AS card_refs,
                 (SELECT STRING_AGG(s.reference, ',' ORDER BY s.reference)
                  FROM card_set s
                  WHERE s.id IN (SELECT DISTINCT c2.set_id FROM card c2 WHERE c2.card_group_id = cg.id)
                 ) AS sets
             FROM card_group cg
             JOIN card c   ON c.card_group_id = cg.id
             JOIN rarity r ON r.id = cg.rarity_id
             WHERE r.reference = 'UNIQUE'
             GROUP BY cg.id, cg.slug
             HAVING COUNT(DISTINCT c.id) >= :min
             ORDER BY COUNT(DISTINCT c.id) DESC
             LIMIT 20",
            ['min' => $minCards],
        );

        $io->table(
            ['Slug', 'Sets', 'Card references'],
            array_map(fn($r) => [$r['slug'], $r['sets'], $r['card_refs']], $samples),
        );

        return Command::SUCCESS;
    }
}
