<?php

namespace App\Command;

use App\Entity\Faction;
use App\Repository\FactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:factions',
    description: 'Create or update factions from the Altered TCG reference data',
)]
class ImportFactionsCommand extends Command
{
    private const FACTIONS = [
        ['code' => 'AX', 'name' => 'Axiom',  'position' => 1],
        ['code' => 'BR', 'name' => 'Bravos',  'position' => 2],
        ['code' => 'LY', 'name' => 'Lyra',    'position' => 3],
        ['code' => 'MU', 'name' => 'Muna',    'position' => 4],
        ['code' => 'OR', 'name' => 'Ordis',   'position' => 5],
        ['code' => 'YZ', 'name' => 'Yzmir',   'position' => 6],
        ['code' => 'NE', 'name' => 'Neutral', 'position' => 7],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FactionRepository $factionRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach (self::FACTIONS as $data) {
            $faction = $this->factionRepository->findOneByCode($data['code']);

            if (!$faction) {
                $faction = new Faction();
                $faction->setCode($data['code']);
                $io->text(sprintf('Creating faction: %s (%s)', $data['name'], $data['code']));
            } else {
                $io->text(sprintf('Updating faction: %s (%s)', $data['name'], $data['code']));
            }

            $faction->setName($data['name']);
            $faction->setPosition($data['position']);

            $this->em->persist($faction);
        }

        $this->em->flush();

        $io->success(sprintf('%d factions created/updated.', count(self::FACTIONS)));

        return Command::SUCCESS;
    }
}
