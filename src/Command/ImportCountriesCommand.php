<?php

namespace App\Command;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import-countries',
    description: 'Add a short description for your command',
)]
class ImportCountriesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pathCountryInfo = 'var/import/countryInfo.txt';
        $pathAlternateNames = 'var/import/alternateNamesV2.txt';

        if (!file_exists($pathCountryInfo) || !file_exists($pathAlternateNames)) {
            $io->error('Fisierele lipsesc din var/import/');
            return Command::FAILURE;
        }

        // --- PASUL 1: COUNTRY INFO ---
        $io->section('Pas 1: Mapare ID-uri ISO');
        $idToIso = [];
        $handle = fopen($pathCountryInfo, "r");
        while (($line = fgets($handle)) !== false) {
            if (str_starts_with($line, '#')) continue;
            $cols = explode("\t", $line);
            if (isset($cols[16])) $idToIso[(int)$cols[16]] = $cols[0];
        }
        fclose($handle);

        // --- PASUL 2: ALTERNATE NAMES (Cu Progress Bar pe Bytes) ---
        $io->section('Pas 2: Citire traduceri din alternateNames.txt');
        $fileSize = filesize($pathAlternateNames);
        $progressBar = new ProgressBar($output, $fileSize);
        $progressBar->setFormat(' %current%/%max% bytes [%bar%] %percent:3s%% -- %message%');
        $progressBar->setMessage('Incepere procesare...');
        $progressBar->start();

        $data = [];
        $handle = fopen($pathAlternateNames, "r");
        $bytesRead = 0;
        $countLines = 0;

        while (($line = fgets($handle)) !== false) {
            $lineLength = strlen($line);
            $bytesRead += $lineLength;

            // Actualizăm bara de progres la fiecare 10.000 de linii pentru a nu incetini procesul
            if (++$countLines % 10000 === 0) {
                $progressBar->setProgress($bytesRead);
                $progressBar->setMessage("Procesare ISO: " . (count($data)));
            }

            $cols = explode("\t", $line);
            $geonameId = (int)($cols[1] ?? 0);

            if (isset($idToIso[$geonameId])) {
                $iso = $idToIso[$geonameId];
                $lang = $cols[2];
                $name = trim($cols[3]);
                $isPreferred = ($cols[4] ?? '0') === '1';
                $isHistoric  = ($cols[7] ?? '0') === '1';
                if ($isHistoric) {
                    continue;
                }

                if ($lang === 'ro' || $lang === 'en') {
                    if ($isPreferred || !isset($data[$iso][$lang])) {
                        $data[$iso][$lang] = $name;
                    }
                }
            }
        }
        fclose($handle);
        $progressBar->finish();
        $io->newLine(2);

        // --- PASUL 3: DOCTRINE SAVE ---
        $io->section('Pas 3: Salvare in Baza de Date');
        $repository = $this->entityManager->getRepository(Country::class);
        $dbProgress = new ProgressBar($output, count($data));
        $dbProgress->start();

        foreach ($data as $iso => $names) {
            $country = $repository->findOneBy(['cc' => $iso]) ?? new Country();

            $nameEn = $names['en'] ?? $iso;
            $nameRo = $names['ro'] ?? $nameEn;

            $country->setCc($iso);
            $country->setName($nameEn);
            $country->setNameRo($nameRo);

            $this->entityManager->persist($country);

            if (($dbProgress->getProgress() % 50) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            $dbProgress->advance();
        }

        $this->entityManager->flush();
        $dbProgress->finish();

        $io->newLine();
        $io->success("Import finalizat cu succes!");

        return Command::SUCCESS;
    }
}
