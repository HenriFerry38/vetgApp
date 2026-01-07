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
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $commande = new Commande();
        $commande->setNumeroCommande(100);
        $commande->setDateCommande(new DateTimeImmutable());
        $commande->setDatePrestation(new \DateTimeImmutable('2026-01-15'));
        $commande->setHeurePrestation(new \DateTimeImmutable('18:30'));
        $commande->setPrixMenu(12.5);
        $commande->setNbPersonne(2);
        $commande->setPrixLivraison(5);
        $this->manager->persist($commande);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "Commande resource created with {$commande->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $commande = $this->repository->findOneBy(['id' => $id]);

        if (!$commande) {
            throw $this->createNotFoundException("No Commande found for {$id} id");
        }

        return $this->json(
            ['message' => "A Commande was found : {$commande->getNumeroCommande()} for {$commande->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $commande = $this->repository->findOneBy(['id' => $id]);

        if (!$commande) {
            throw $this->createNotFoundException("No Commande found for {$id} id");
        }

        $commande->setNumeroCommande(1);
        $this->manager->flush();

        return $this->redirectToRoute('app_api_commande_show', ['id' => $commande->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if (!$commande) {
            throw $this->createNotFoundException("No Commande found for {$id} id");
        }
        $this->manager->remove($commande);
        $this->manager->flush();
        return $this->json(['message' => "Commande resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

