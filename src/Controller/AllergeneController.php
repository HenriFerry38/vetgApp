<?php

namespace App\Controller;
use App\Repository\AllergeneRepository;
use App\Entity\Allergene;
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

#[Route('/api/allergene', name: 'app_api_allergene_')]
class AllergeneController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private AllergeneRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $allergene = new Allergene();
        $allergene->setLibelle('Poissons');
        $allergene->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($allergene);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "Allergene resource created with {$allergene->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);

        if (!$allergene) {
            throw $this->createNotFoundException("No allergene found for {$id} id");
        }

        return $this->json(
            ['message' => "A allergene was found : {$allergene->getLibelle()} for {$allergene->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);

        if (!$allergene) {
            throw $this->createNotFoundException("No allergene found for {$id} id");
        }

        $allergene->setLibelle('allergene name updated');
        $allergene->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_allergene_show', ['id' => $allergene->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $allergene = $this->repository->findOneBy(['id' => $id]);
        if (!$allergene) {
            throw $this->createNotFoundException("No allergene found for {$id} id");
        }
        $this->manager->remove($allergene);
        $this->manager->flush();
        return $this->json(['message' => "Allergene resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

