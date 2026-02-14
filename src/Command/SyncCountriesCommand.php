<?php

namespace App\Command;

use App\Controller\CountryMappingController;
use App\Entity\TourOperator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'sync-countries')]
class SyncCountriesCommand extends Command
{
    // Injectăm Controllerul pentru a-i folosi funcția processSyncForOperator
    public function __construct(
        private EntityManagerInterface $em,
        private CountryMappingController $countryController
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $operators = $this->em->getRepository(TourOperator::class)->findAll();

        foreach ($operators as $operator) {
            $io->note("Syncing operator: " . $operator->getName());

            // Apelăm funcția din Controller
            $stats = $this->countryController->processSyncForOperator($operator);

            $io->text(sprintf(
                "Result: Created %d, Updated %d, Skipped %d",
                $stats['created'], $stats['updated'], $stats['skipped']
            ));
        }

        $io->success('Nightly sync finished.');
        return Command::SUCCESS;
    }
}
