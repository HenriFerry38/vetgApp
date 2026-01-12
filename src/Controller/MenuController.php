<?php

namespace App\Controller;

use App\Repository\MenuRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use App\Entity\Menu;
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

#[Route('/api/menu', name: 'app_api_menu_')]
class MenuController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private MenuRepository $repository,
        private RegimeRepository $regimeRepository,
        private ThemeRepository $themeRepository,
         private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    public function new(Request $request): JsonResponse
    {   
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $menu = $this->serializer->deserialize($request->getContent(), Menu::class, 'json');
        $menu->setCreatedAt(new DateTimeImmutable());
        
        $regimeId = $data['regimeId'] ?? null;
        $themeId  = $data['themeId'] ?? null;
        
        if (!$regimeId || !$themeId) {
            return new JsonResponse(['error' => 'regimeId et themeId sont obligatoires'], Response::HTTP_BAD_REQUEST);
        }

        $regime = $this->regimeRepository->find($regimeId);
        $theme  = $this->themeRepository->find($themeId);

        if (!$regime || !$theme) {
            return new JsonResponse(['error' => 'Regime ou Theme introuvable'], Response::HTTP_BAD_REQUEST);
        }
        
        $menu->setRegime($regime);
        $menu->setTheme($theme);
        $this->manager->persist($menu);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($menu, 'json', [
            'circular_reference_handler' => function ($object) {
                return method_exists($object, 'getId') ? $object->getId() : null;
            },
        ]);
        $location = $this->urlGenerator->generate(
            'app_api_menu_show',
            ['id' => $menu->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        
        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if ($menu) {
            $responseData = $this->serializer->serialize($menu, 'json',[
                'circular_reference_handler' => function ($object) {
                return method_exists($object, 'getId') ? $object->getId() : null;
            },
        ]);
            

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if ($menu) {
            
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse(['error' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
            }
            $menu = $this->serializer->deserialize(
                $request->getContent(),
                Menu::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $menu]
            );
            
            if (array_key_exists('regimeId', $data)) {
                $regime = $data['regimeId'] ? $this->regimeRepository->find($data['regimeId']) : null;
                if (!$regime) {
                    return new JsonResponse(['error' => 'Regime introuvable'], Response::HTTP_BAD_REQUEST);
                }
                $menu->setRegime($regime);
            }

            if (array_key_exists('themeId', $data)) {
                $theme = $data['themeId'] ? $this->themeRepository->find($data['themeId']) : null;
                if (!$theme) {
                    return new JsonResponse(['error' => 'Theme introuvable'], Response::HTTP_BAD_REQUEST);
                }
                $menu->setTheme($theme);
            }

            $menu->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $menu = $this->repository->findOneBy(['id' => $id]);
        if ($menu) {
            $this->manager->remove($menu);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}

