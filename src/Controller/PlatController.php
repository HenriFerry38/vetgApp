<?php

namespace App\Controller;
use App\Repository\PlatRepository;
use App\Entity\Plat;
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

#[Route('/api/plat', name: 'app_api_plat_')]
class PlatController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private PlatRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $plat = new Plat();
        $plat->setTitre('Poulet Roti');
        $plat->setPhoto('Poulet.jpg');
        $plat->setCreatedAt(new DateTimeImmutable());

        $this->manager->persist($plat);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "plat resource created with {$plat->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $plat = $this->repository->findOneBy(['id' => $id]);

        if (!$plat) {
            throw $this->createNotFoundException("No plat found for {$id} id");
        }

        return $this->json(
            ['message' => "A plat was found : {$plat->getTitre()} for {$plat->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $plat = $this->repository->findOneBy(['id' => $id]);

        if (!$plat) {
            throw $this->createNotFoundException("No plat found for {$id} id");
        }

        $plat->setTitre('Poulet tout cours');
        $this->manager->flush();

        return $this->redirectToRoute('app_api_plat_show', ['id' => $plat->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if (!$plat) {
            throw $this->createNotFoundException("No plat found for {$id} id");
        }
        $this->manager->remove($plat);
        $this->manager->flush();
        return $this->json(['message' => "plat resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

