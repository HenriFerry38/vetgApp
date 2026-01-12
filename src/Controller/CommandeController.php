<?php

namespace App\Controller;
use App\Repository\CommandeRepository;
use App\Entity\Commande;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/commande', name: 'app_api_commande_')]
class CommandeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CommandeRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {   
        $commande = $this->serializer->deserialize($request->getContent(), Commande::class, 'json');
       
        $this->manager->persist($commande);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($commande, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_commande_show',
            ['id' => $commande->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            $responseData = $this->serializer->serialize($commande, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            $commande = $this->serializer->deserialize(
                $request->getContent(),
                Commande::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $commande]
            );
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            $this->manager->remove($commande);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}