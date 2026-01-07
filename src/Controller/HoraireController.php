<?php

namespace App\Controller;
use App\Repository\HoraireRepository;
use App\Entity\Horaire;
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

#[Route('/api/horaire', name: 'app_api_horaire_')]
class HoraireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private HoraireRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $horaire = new Horaire();
        $horaire->setJour('Lundi');
        $horaire->setHeureOuverture('10h00');
        $horaire->setHeureFermeture('22h00');
        $horaire->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($horaire);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "horaire resource created with {$horaire->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);

        if (!$horaire) {
            throw $this->createNotFoundException("No horaire found for {$id} id");
        }

        return $this->json(
            ['message' => "A horaire was found : {$horaire->getJour()} for {$horaire->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);

        if (!$horaire) {
            throw $this->createNotFoundException("No horaire found for {$id} id");
        }

        $horaire->setJour('Jour updated');
        $horaire->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_horaire_show', ['id' => $horaire->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if (!$horaire) {
            throw $this->createNotFoundException("No horaire found for {$id} id");
        }
        $this->manager->remove($horaire);
        $this->manager->flush();
        return $this->json(['message' => "horaire resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

