<?php

namespace App\Controller;

use App\Entity\Country;
use App\Repository\CountryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/countries')]
class CountryController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient
    ) {}

    public function importFromGeonames(): string
    {
        $url = "https://download.geonames.org/export/dump/countryInfo.txt";

        $response = $this->httpClient->request('GET', $url);
        $content = $response->getContent();

        $lines = explode("\n", $content);
        $repository = $this->entityManager->getRepository(Country::class);

        foreach ($lines as $line) {
            // ignore comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $columns = explode("\t", $line);

            $isoCode = $columns[0]; // ISO
            $name = $columns[4];    // Country Name

            // Căutăm dacă există deja
            $country = $repository->findOneBy(['cc' => $isoCode]);

            if (!$country) {
                $country = new Country();
                $country->setCc($isoCode);
                $this->entityManager->persist($country);
            }

            $country->setName($name);
        }

        // Trimitem toate modificările la baza de date într-o singură tranzacție
        $this->entityManager->flush();

        return "Import nomenclator țări finalizat cu succes!";
    }

    #[Route('', methods: ['GET'])]
    public function index(CountryRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll());
    }

    /*
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json($product);
    }*/

    #[Route('', methods: ['POST'])]
    public function store(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $country = new Country();
        $country->setCc($data['cc']);
        $country->setName($data['name']);

        $em->persist($country);
        $em->flush();

        return $this->json($country, 201);
    }
    /*
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        Product $product,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $product->setName($data['name'] ?? $product->getName());
        $product->setPrice($data['price'] ?? $product->getPrice());

        $em->flush();

        return $this->json($product);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($product);
        $em->flush();

        return $this->json(null, 204);
    }*/
}
