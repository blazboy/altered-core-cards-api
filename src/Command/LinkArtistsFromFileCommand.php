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
    name: 'app:link:artists:file',
    description: 'Link cards to artists using datas/artists_cards.json (artist → [references] mapping)',
)]
class LinkArtistsFromFileCommand extends Command
{
    private const DATA_FILE   = __DIR__ . '/../../datas/artists_cards.json';
    private const BATCH_SIZE  = 500;

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('artist', null, InputOption::VALUE_OPTIONAL, 'Process only this artist name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $raw = file_get_contents(self::DATA_FILE);
        if ($raw === false) {
            $io->error('Cannot read ' . self::DATA_FILE);
            return Command::FAILURE;
        }

        /** @var array<string, string[]> $mapping */
        $mapping = json_decode($raw, true);
        if (!is_array($mapping)) {
            $io->error('Invalid JSON in ' . self::DATA_FILE);
            return Command::FAILURE;
        }

        $filterArtist = $input->getOption('artist');
        if ($filterArtist) {
            $mapping = array_filter($mapping, static fn($k) => $k === $filterArtist, ARRAY_FILTER_USE_KEY);
        }

        $io->title(sprintf('Linking artists from file (%d artist(s))', count($mapping)));

        $totalLinked  = 0;
        $totalSkipped = 0;
        $totalUnknown = 0;

        // Pre-load all artist ids keyed by reference (= name)
        $artistRows = $this->connection->fetchAllKeyValue('SELECT reference, id FROM artist');

        foreach ($mapping as $artistName => $references) {
            if (empty($references)) {
                $io->text(sprintf('  <comment>%s</comment> — no references, skipping', $artistName));
                continue;
            }

            $artistId = $artistRows[$artistName] ?? null;
            if ($artistId === null) {
                $io->warning(sprintf('Artist not found in DB: "%s"', $artistName));
                continue;
            }

            $io->section($artistName);

            $linked  = 0;
            $skipped = 0;
            $unknown = 0;

            foreach (array_chunk($references, self::BATCH_SIZE) as $chunk) {
                // Resolve card ids for this chunk of references
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $cardRows = $this->connection->fetchAllKeyValue(
                    "SELECT reference, id FROM card WHERE reference IN ($placeholders)",
                    $chunk,
                );

                foreach ($chunk as $reference) {
                    $cardId = $cardRows[$reference] ?? null;
                    if ($cardId === null) {
                        ++$unknown;
                        continue;
                    }

                    $rows = $this->connection->executeStatement(
                        'INSERT INTO card_artist (card_id, artist_id) VALUES (?, ?) ON CONFLICT DO NOTHING',
                        [$cardId, $artistId],
                    );
                    if ($rows > 0) {
                        ++$linked;
                    } else {
                        ++$skipped;
                    }
                }
            }

            $io->text(sprintf(
                '  → %d linked, %d already existed, %d unknown references',
                $linked, $skipped, $unknown,
            ));

            $totalLinked  += $linked;
            $totalSkipped += $skipped;
            $totalUnknown += $unknown;
        }

        $io->success(sprintf(
            '%d link(s) created, %d already existed, %d unknown references.',
            $totalLinked, $totalSkipped, $totalUnknown,
        ));

        return Command::SUCCESS;
    }
}
