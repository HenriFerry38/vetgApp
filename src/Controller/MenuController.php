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
    #[OA\Post(
        path: '/api/menu',
        summary: "Créer un nouveau menu",
        description: "Crée un menu, associe un régime et un thème via leurs identifiants, puis retourne la ressource créée.",
        tags: ['Menu'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titre', 'nb_personne_mini', 'prix_par_personne', 'description', 'regimeId', 'themeId'],
                properties: [
                    new OA\Property(
                        property: 'titre',
                        type: 'string',
                        example: 'Menu de Noël'
                    ),
                    new OA\Property(
                        property: 'nb_personne_mini',
                        type: 'integer',
                        example: 4
                    ),
                    new OA\Property(
                        property: 'prix_par_personne',
                        type: 'number',
                        format: 'float',
                        example: 12.50
                    ),
                    new OA\Property(
                        property: 'description',
                        type: 'string',
                        example: 'Un menu festif complet avec entrée, plat, dessert.'
                    ),
                    new OA\Property(
                        property: 'quantite_restaurant',
                        type: 'integer',
                        nullable: true,
                        example: 20
                    ),
                    new OA\Property(
                        property: 'regimeId',
                        type: 'integer',
                        description: "Identifiant du régime associé",
                        example: 1
                    ),
                    new OA\Property(
                        property: 'themeId',
                        type: 'integer',
                        description: "Identifiant du thème associé",
                        example: 2
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Menu créé",
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: "URL de la ressource créée",
                        schema: new OA\Schema(type: 'string', format: 'uri')
                    )
                ],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 10),
                        new OA\Property(property: 'titre', type: 'string', example: 'Menu de Noël'),
                        new OA\Property(property: 'nb_personne_mini', type: 'integer', example: 4),
                        new OA\Property(property: 'prix_par_personne', type: 'string', example: '12.50'),
                        new OA\Property(property: 'description', type: 'string', example: 'Un menu festif complet avec entrée, plat, dessert.'),
                        new OA\Property(property: 'quantite_restaurant', type: 'integer', nullable: true, example: 20),

                        // Comme tu utilises circular_reference_handler, regime/theme peuvent ressortir en ID
                        new OA\Property(property: 'regime', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'theme', type: 'integer', nullable: true, example: 2),

                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide (JSON invalide, champs obligatoires manquants, régime/thème introuvable)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'regimeId et themeId sont obligatoires')
                    ]
                )
            )
        ]
    )]
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
    #[OA\Get(
        path: '/api/menu/{id}',
        summary: "Afficher un menu par ID",
        description: "Retourne un menu à partir de son identifiant",
        tags: ['Menu'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du menu",
                schema: new OA\Schema(type: 'integer', example: 10)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Menu trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 10),
                        new OA\Property(property: 'titre', type: 'string', example: 'Menu de Noël'),
                        new OA\Property(property: 'nb_personne_mini', type: 'integer', example: 4),
                        new OA\Property(property: 'prix_par_personne', type: 'string', example: '12.50'),
                        new OA\Property(property: 'description', type: 'string', example: 'Un menu festif complet avec entrée, plat, dessert.'),
                        new OA\Property(property: 'quantite_restaurant', type: 'integer', nullable: true, example: 20),

                        // Avec circular_reference_handler, ces relations ressortent souvent en ID
                        new OA\Property(property: 'regime', type: 'integer', nullable: true, example: 1),
                        new OA\Property(property: 'theme', type: 'integer', nullable: true, example: 2),

                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Menu non trouvé"
            )
        ]
    )]
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
    #[OA\Put(
        path: '/api/menu/{id}',
        summary: "Modifier un menu par ID",
        description: "Met à jour un menu existant. Permet aussi de modifier les associations via regimeId et themeId.",
        tags: ['Menu'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du menu",
                schema: new OA\Schema(type: 'integer', example: 10)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                // Tu acceptes un PUT “souple” (tu testes array_key_exists), donc on ne force pas required ici.
                properties: [
                    new OA\Property(property: 'titre', type: 'string', example: 'Menu de Noël (édition 2026)'),
                    new OA\Property(property: 'nb_personne_mini', type: 'integer', example: 6),
                    new OA\Property(property: 'prix_par_personne', type: 'number', format: 'float', example: 14.90),
                    new OA\Property(property: 'description', type: 'string', example: 'Menu festif mis à jour avec options supplémentaires.'),
                    new OA\Property(property: 'quantite_restaurant', type: 'integer', nullable: true, example: 25),

                    // Champs spéciaux gérés dans ton controller
                    new OA\Property(
                        property: 'regimeId',
                        type: 'integer',
                        nullable: true,
                        description: "Nouvel identifiant du régime (si fourni).",
                        example: 1
                    ),
                    new OA\Property(
                        property: 'themeId',
                        type: 'integer',
                        nullable: true,
                        description: "Nouvel identifiant du thème (si fourni).",
                        example: 2
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Menu modifié (pas de contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide (JSON invalide, régime/thème introuvable)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Regime introuvable')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Menu non trouvé"
            )
        ]
    )]
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
    #[OA\Delete(
        path: '/api/menu/{id}',
        summary: "Supprimer un menu",
        description: "Supprime un menu à partir de son identifiant",
        tags: ['Menu'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du menu",
                schema: new OA\Schema(type: 'integer', example: 10)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Menu supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Menu non trouvé"
            )
        ]
    )]
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

