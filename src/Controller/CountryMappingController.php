<?php

namespace App\Controller;

use App\Entity\Country;
use App\Entity\CountryMapping;
use App\Entity\TourOperator;
use App\Repository\CountryMappingRepository;
use App\Repository\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/country-mapping')]
class CountryMappingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CountryMappingRepository $mappingRepository,
        private CountryRepository $countryRepository
    ) {}

    #[Route('/list', name: 'api_country_mapping_pending', methods: ['GET'])]
    public function getPending(): JsonResponse
    {
        $mappings = $this->mappingRepository->findBy(
            ['match' => 'ASC']
        );

        $data = [];
        foreach ($mappings as $mapping) {
            $data[] = [
                'id' => $mapping->getId(),
                'provider' => $mapping->getTourOperator()->getName(),
                'externalName' => $mapping->getExternalName(),
                'externalCode' => $mapping->getExternalCode(),
                'match' => $mapping->getMatch(),
                // Sugestia curentă (dacă scriptul de noapte a găsit ceva)
                'selectedId' => $mapping->getCountry()?->getId(),
                'suggestions' => $this->getNearbySuggestions($mapping)
            ];
        }

        return $this->json([
            'items' => $data,
            'lastSync' => 'Today, 00:00'
        ]);
    }

    private function getNearbySuggestions(CountryMapping $mapping): array
    {
        // Aici poți returna țările standard pentru dropdown-ul Vue
        // Pentru performanță, poți limita la top 5 bazat pe nume
        $suggestions = $this->countryRepository->createQueryBuilder('c')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $output = [];
        foreach ($suggestions as $s) {
            $output[] = [
                'id' => $s->getId(),
                'name' => $s->getNameEn(), // sau NameRo
                'cc' => $s->getCc()
            ];
        }
        return $output;
    }

    #[Route('/{id}/approve', name: 'api_country_mapping_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        $mapping = $this->mappingRepository->find($id);
        if (!$mapping) {
            return $this->json(['error' => 'Mapping not found'], 404);
        }

        $payload = json_decode($request->getContent(), true);
        $countryId = $payload['countryId'] ?? null;

        if (!$countryId) {
            return $this->json(['error' => 'No country selected'], 400);
        }

        $country = $this->countryRepository->find($countryId);
        if (!$country) {
            return $this->json(['error' => 'Standard Country not found'], 404);
        }

        // Actualizăm statusul
        $mapping->setCountry($country);
        $mapping->setStatus(CountryMapping::STATUS_MANUAL); // Marcat ca verificat de om
        $mapping->setConfidence(100);

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/reject', name: 'api_country_mapping_reject', methods: ['DELETE'])]
    public function reject(int $id): JsonResponse
    {
        $mapping = $this->mappingRepository->find($id);
        if ($mapping) {
            $this->entityManager->remove($mapping);
            $this->entityManager->flush();
        }

        return $this->json(['success' => true]);
    }

    /**
     * Endpoint API pentru declanșare manuală din interfață
     */
    #[Route('/sync-all', name: 'api_country_mapping_sync_all', methods: ['POST'])]
    public function syncAllAction(): JsonResponse
    {
        $operators = $this->entityManager->getRepository(TourOperator::class)->findAll();
        $results = [];

        foreach ($operators as $operator) {
            $results[$operator->getName()] = $this->processSyncForOperator($operator);
        }

        return $this->json([
            'status' => 'success',
            'details' => $results
        ]);
    }

    /**
     * Aceasta este funcția "miez" care va fi apelată și de Command
     */
    public function processSyncForOperator(TourOperator $operator): array
    {
        // 1. Luăm țările standard pentru matching
        $standardCountries = $this->countryRepository->findAll();

        // 2. Simulare date externe (Aici înlocuiești cu apelul tău real către API-ul lor)
        $externalData = $this->fetchExternalData($operator);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($externalData as $item) {
            $mapping = $this->mappingRepository->findOneBy([
                'tourOperator' => $operator,
                'externalCode' => $item['code']
            ]) ?? new CountryMapping();

            // Dacă e mapat manual, nu ne atingem de el
            if ($mapping->getStatus() === CountryMapping::STATUS_MANUAL) {
                $stats['skipped']++;
                continue;
            }

            $isNew = !$mapping->getId();
            $mapping->setTourOperator($operator);
            $mapping->setExternalCode($item['code']);
            $mapping->setExternalName($item['name']);

            // Fuzzy Match Logic
            $match = $this->calculateFuzzyMatch($item['name'], $standardCountries);

            $mapping->setCountry($match['country']);
            $mapping->setMatch($match['score']);
            $mapping->setStatus($match['score'] >= 98 ? CountryMapping::STATUS_AUTO : CountryMapping::STATUS_PENDING);

            if ($isNew) {
                $this->entityManager->persist($mapping);
                $stats['created']++;
            } else {
                $stats['updated']++;
            }
        }

        $this->entityManager->flush();
        return $stats;
    }

    private function calculateFuzzyMatch(string $externalName, array $standardCountries): array
    {
        $bestMatch = null;
        $highestScore = 0;

        foreach ($standardCountries as $country) {
            $s1 = mb_strtolower(trim($externalName));
            $s2 = mb_strtolower(trim($country->getNameRo()));

            $dist = levenshtein($s1, $s2);
            $maxLen = max(strlen($s1), strlen($s2));
            $score = ($maxLen > 0) ? (int)((1 - $dist / $maxLen) * 100) : 0;

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $country;
            }
        }

        return ['country' => $bestMatch, 'score' => $highestScore];
    }

    private function fetchExternalData(TourOperator $operator): array
    {
        // Aici pui logica de tras date (cURL, Guzzle, etc.)
        return [
            ['code' => 'RO', 'name' => 'Rumanien'],
            ['code' => 'BG', 'name' => 'Bulgaria'],
        ];
    }
}
