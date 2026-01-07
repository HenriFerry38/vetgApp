<?php

namespace App\Controller;
use App\Repository\AvisRepository;
use App\Entity\Avis;
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

#[Route('/api/avis', name: 'app_api_avis_')]
class AvisController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AvisRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $avis = new Avis();
        $avis->setNote(5);
        $avis->setDescription('J\'ai adoré le menu de Nowel');
        $avis->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($avis);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "Avis resource created with {$avis->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $avis = $this->repository->findOneBy(['id' => $id]);

        if (!$avis) {
            throw $this->createNotFoundException("No avis found for {$id} id");
        }

        return $this->json(
            ['message' => "A avis was found : {$avis->getDescription()} for {$avis->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $avis = $this->repository->findOneBy(['id' => $id]);

        if (!$avis) {
            throw $this->createNotFoundException("No avis found for {$id} id");
        }

        $avis->setDescription('Avis updated');
        $avis->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_avis_show', ['id' => $avis->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $avis = $this->repository->findOneBy(['id' => $id]);
        if (!$avis) {
            throw $this->createNotFoundException("No avis found for {$id} id");
        }
        $this->manager->remove($avis);
        $this->manager->flush();
        return $this->json(['message' => "Avis resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

