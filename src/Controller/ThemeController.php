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
    #[OA\Post(
        path: '/api/theme',
        summary: "Créer un nouveau thème",
        description: "Crée un thème et retourne la ressource créée",
        tags: ['Theme'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['libelle'],
                properties: [
                    new OA\Property(
                        property: 'libelle',
                        type: 'string',
                        example: 'Noël'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Thème créé",
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: "URL de la ressource créée",
                        schema: new OA\Schema(type: 'string', format: 'uri')
                    )
                ],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'libelle', type: 'string', example: 'Noël'),
                        new OA\Property(
                            property: 'createdAt',
                            type: 'string',
                            format: 'date-time'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide"
            )
        ]
    )]
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
    #[OA\Get(
        path: '/api/theme/{id}',
        summary: "Afficher un thème par ID",
        description: "Retourne un thème à partir de son identifiant",
        tags: ['Theme'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du thème",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Thème trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'libelle', type: 'string', example: 'Noël'),
                        new OA\Property(
                            property: 'createdAt',
                            type: 'string',
                            format: 'date-time'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Thème non trouvé"
            )
        ]
    )]
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
    #[OA\Put(
        path: '/api/theme/{id}',
        summary: "Modifier un thème par ID",
        description: "Met à jour un thème existant à partir de son identifiant",
        tags: ['Theme'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du thème",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['libelle'],
                properties: [
                    new OA\Property(
                        property: 'libelle',
                        type: 'string',
                        example: 'Pâques'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Thème modifié avec succès (aucun contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide"
            ),
            new OA\Response(
                response: 404,
                description: "Thème non trouvé"
            )
        ]
    )]

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
    #[OA\Delete(
        path: '/api/theme/{id}',
        summary: "Supprimer un thème par ID",
        description: "Supprime un thème à partir de son identifiant",
        tags: ['Theme'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du thème",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Thème supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Thème non trouvé"
            )
        ]
    )]
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
    #[Route('', name: 'app_api_theme_list', methods: ['GET'])]
    #[Security("is_granted('ROLE_EMPLOYEE') or is_granted('ROLE_ADMIN')")]
    #[OA\Get(
        path: '/api/theme',
        summary: "Lister tous les thèmes",
        description: "Retourne la liste complète des thèmes. Accessible uniquement aux employés et administrateurs.",
        tags: ['Theme'],
        security: [['X-AUTH-TOKEN' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des thèmes",
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'libelle', type: 'string', example: 'Noël'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-02-13T14:41:47.399Z')
                        ],
                        type: 'object'
                    )
                )
            ),
            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),
            new OA\Response(
                response: 403,
                description: "Accès refusé"
            )
        ]
    )]

    public function list(ThemeRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), Response::HTTP_OK, [], ['groups' => ['theme:read']]);
    }
}