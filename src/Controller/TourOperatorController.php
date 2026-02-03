<?php

namespace App\Controller;

use App\Entity\TourOperator;
use App\Repository\TourOperatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tour-op')]
final class TourOperatorController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(TourOperatorRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll());
    }

    #[Route('', methods: ['POST'])]
    public function store(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $entity = new TourOperator();
        $entity->setName($data['name']);
        $entity->setApiUrl($data['api_url']);
        $entity->setUsername($data['username']);
        $entity->setPassword($data['password']);

        $em->persist($entity);
        $em->flush();

        return $this->json($entity, 201);
    }
}
