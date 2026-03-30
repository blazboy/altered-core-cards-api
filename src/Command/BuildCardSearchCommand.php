<?php

namespace App\Command;

use App\Service\CardSearchUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:card-search:build',
    description: 'Rebuild the card_search flat table used for effect/keyword filtering',
)]
final class BuildCardSearchCommand extends Command
{
    public function __construct(private readonly CardSearchUpdater $updater)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Rebuilding card_search table…');

        $count = $this->updater->rebuild();

        $io->success(sprintf('%d rows inserted.', $count));

        return Command::SUCCESS;
    }
}
