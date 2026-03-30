<?php

namespace App\Command;

use App\Service\EffectParser;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:effects:parse',
    description: 'Parse keywords from main_effect text and store them',
)]
class ParseEffectsCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly Connection   $connection,
        private readonly EffectParser $parser,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Parsing effect keywords');

        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM main_effect WHERE text_fr IS NOT NULL');
        $io->progressStart($total);

        $offset  = 0;
        $updated = 0;

        while (true) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT id, text_fr FROM main_effect WHERE text_fr IS NOT NULL ORDER BY id LIMIT ? OFFSET ?',
                [self::BATCH_SIZE, $offset]
            );

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $keywords = $this->parser->parseKeywords($row['text_fr']);

                $this->connection->executeStatement(
                    'UPDATE main_effect SET keywords = ? WHERE id = ?',
                    [
                        empty($keywords) ? null : json_encode($keywords, JSON_UNESCAPED_UNICODE),
                        $row['id'],
                    ]
                );

                $updated++;
                $io->progressAdvance();
            }

            $offset += self::BATCH_SIZE;
        }

        $io->progressFinish();
        $io->success(sprintf('%d effects parsed.', $updated));

        return Command::SUCCESS;
    }
}
