<?php

namespace App\Controller;
use App\Repository\RegimeRepository;
use App\Entity\Regime;
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

#[Route('/api/regime', name: 'app_api_regime_')]
class RegimeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RegimeRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $regime = new Regime();
        $regime->setLibelle('Vegetarien');
        $regime->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($regime);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "Regime resource created with {$regime->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $regime = $this->repository->findOneBy(['id' => $id]);

        if (!$regime) {
            throw $this->createNotFoundException("No regime found for {$id} id");
        }

        return $this->json(
            ['message' => "A Regime was found : {$regime->getLibelle()} for {$regime->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $regime = $this->repository->findOneBy(['id' => $id]);

        if (!$regime) {
            throw $this->createNotFoundException("No regime found for {$id} id");
        }

        $regime->setLibelle('Regime name updated');
        $regime->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_regime_show', ['id' => $regime->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if (!$regime) {
            throw $this->createNotFoundException("No regime found for {$id} id");
        }
        $this->manager->remove($regime);
        $this->manager->flush();
        return $this->json(['message' => "Regime resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

