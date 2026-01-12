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
        private ThemeRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {   
        $theme = $this->serializer->deserialize($request->getContent(), Theme::class, 'json');
        $theme->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($theme);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($theme, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_theme_show',
            ['id' => $theme->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if ($theme) {
            $responseData = $this->serializer->serialize($theme, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if ($theme) {
            $theme = $this->serializer->deserialize(
                $request->getContent(),
                Theme::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $theme]
            );
            $theme->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);

    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $theme = $this->repository->findOneBy(['id' => $id]);
        if ($theme) {
            $this->manager->remove($theme);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}