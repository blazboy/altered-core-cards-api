<?php

namespace App\Command;

use App\Repository\CardDocumentRepository;
use App\Service\MeilisearchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:meilisearch:index',
    description: 'Index all cards into Meilisearch',
)]
final class MeilisearchIndexCommand extends Command
{
    public function __construct(
        private readonly MeilisearchService $meilisearch,
        private readonly CardDocumentRepository $cardDocumentRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('configure', null, InputOption::VALUE_NONE, 'Configure index settings before indexing');
        $this->addOption('clear', null, InputOption::VALUE_NONE, 'Delete all documents before re-indexing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Indexing cards into Meilisearch…');

        if ($input->getOption('configure')) {
            $io->text('Configuring index attributes…');
            $this->meilisearch->configureIndex();
        }

        if ($input->getOption('clear')) {
            $io->text('Clearing existing documents…');
            $this->meilisearch->getIndex()->deleteAllDocuments();
        }

        $total = $this->cardDocumentRepository->countAll();
        $io->text(sprintf('Streaming %d cards…', $total));

        $progressBar = $io->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %elapsed:6s%/%estimated:-6s%');
        $progressBar->start();

        $indexed = 0;
        foreach ($this->cardDocumentRepository->streamDocuments() as $batch) {
            // Encode ourselves with JSON_INVALID_UTF8_IGNORE so that any residual
            // invalid byte sequences are silently dropped instead of producing lone
            // surrogates that PHP considers valid but Meilisearch's Rust parser rejects.
            $json = json_encode($batch, JSON_INVALID_UTF8_IGNORE);

            if ($json === false) {
                foreach ($batch as $doc) {
                    if (json_encode($doc, JSON_INVALID_UTF8_IGNORE) === false) {
                        $io->warning(sprintf('Card ID %d skipped (JSON error: %s)', $doc['id'], json_last_error_msg()));
                    }
                }
                $indexed += count($batch);
                $progressBar->advance(count($batch));
                continue;
            }

            $this->meilisearch->getIndex()->addDocumentsJson($json);
            $indexed += count($batch);
            $progressBar->advance(count($batch));
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success(sprintf('%d cards indexed.', $indexed));

        return Command::SUCCESS;
    }
}
