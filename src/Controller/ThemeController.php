<?php

namespace App\Controller;
use App\Repository\ThemeRepository;
use App\Entity\Theme;
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

#[Route('/api/theme', name: 'app_api_theme_')]
class ThemeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private themeRepository $repository,
        /* private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,*/
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(): Response
    {   
        $theme = new Theme();
        $theme->setLibelle('Repas de Noël');
        $theme->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($theme);
        
        $this->manager->flush();
        return $this->json(
            ['message' => "Theme resource created with {$theme->getId()} id"],
            Response::HTTP_CREATED,
        );
    
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $theme = $this->repository->findOneBy(['id' => $id]);

        if (!$theme) {
            throw $this->createNotFoundException("No Theme found for {$id} id");
        }

        return $this->json(
            ['message' => "A Theme was found : {$theme->getLibelle()} for {$theme->getId()} id"]
        );
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id): Response
    {
        $theme = $this->repository->findOneBy(['id' => $id]);

        if (!$theme) {
            throw $this->createNotFoundException("No theme found for {$id} id");
        }

        $theme->setLibelle('Theme name updated');
        $theme->setUpdatedAt(new DateTimeImmutable());
        $this->manager->flush();

        return $this->redirectToRoute('app_api_theme_show', ['id' => $theme->getId()]);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if (!$theme) {
            throw $this->createNotFoundException("No theme found for {$id} id");
        }
        $this->manager->remove($theme);
        $this->manager->flush();
        return $this->json(['message' => "Theme resource deleted"], Response::HTTP_NO_CONTENT);
    }
}

