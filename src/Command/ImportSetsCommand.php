<?php

namespace App\Command;

use App\Entity\Set;
use App\Repository\SetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:sets',
    description: 'Import card sets from a CSV file',
)]
class ImportSetsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SetRepository $setRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL,
            'Path to the CSV file',
            'datas/card_set.csv'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = $input->getArgument('file');

        if (!file_exists($file)) {
            $io->error(sprintf('File not found: %s', $file));
            return Command::FAILURE;
        }

        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        if (empty($rows)) {
            $io->warning('No rows found in the CSV file.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($rows));

        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            $set = $this->setRepository->findOneBy(['alteredId' => $row['altered_id']]);

            if ($set === null) {
                $set = new Set();
                $set->setCreationDate(new \DateTimeImmutable($row['creation_date']));
                $created++;
            } else {
                $updated++;
            }

            $set->setAlteredId($row['altered_id']);
            $set->setName($row['name']);
            $set->setNameEn($row['name_en'] ?: null);
            $set->setCode($row['code'] ?: null);
            $set->setIsActive($row['is_active'] === '1');
            $set->setReference($row['reference']);
            $set->setIllustration($row['illustration'] ?: null);
            $set->setIllustrationPath($row['illustration_path'] ?: null);
            $set->setDate($row['date'] ? new \DateTimeImmutable($row['date']) : null);
            $set->setCardGoogleSheets($this->parseJson($row['card_google_sheets']));

            $this->em->persist($set);
            $io->progressAdvance();
        }

        $this->em->flush();
        $io->progressFinish();

        $io->success(sprintf('Import done: %d created, %d updated.', $created, $updated));

        return Command::SUCCESS;
    }

    private function parseJson(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
