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
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/regime',
        summary: "Créer un nouveau régime",
        description: "Crée un régime et retourne la ressource créée",
        tags: ['Regime'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['libelle'],
                properties: [
                    new OA\Property(
                        property: 'libelle',
                        type: 'string',
                        example: 'Végétarien'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Régime créé",
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
                        new OA\Property(property: 'libelle', type: 'string', example: 'Végétarien'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
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
        $regime = $this->serializer->deserialize($request->getContent(), Regime::class, 'json');
        $regime->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($regime);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($regime, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_regime_show',
            ['id' => $regime->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/regime/{id}',
        summary: "Afficher un régime par ID",
        description: "Retourne un régime à partir de son identifiant",
        tags: ['Regime'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du régime",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Régime trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'libelle', type: 'string', example: 'Végétarien'),
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
                description: "Régime non trouvé"
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if ($regime) {
            $responseData = $this->serializer->serialize($regime, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/regime/{id}',
        summary: "Modifier un régime par ID",
        description: "Met à jour un régime existant à partir de son identifiant",
        tags: ['Regime'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du régime",
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
                        example: 'Sans gluten'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Régime modifié avec succès (aucun contenu retourné)"
            ),
            new OA\Response(
                response: 404,
                description: "Régime non trouvé"
            )
        ]
    )]
    public function edit(int $id, Request $request): JsonResponse
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if ($regime) {
            $regime = $this->serializer->deserialize(
                $request->getContent(),
                Regime::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $regime]
            );
            $regime->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/regime/{id}',
        summary: "Supprimer un régime par ID",
        description: "Supprime un régime à partir de son identifiant",
        tags: ['Regime'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du régime",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Régime supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Régime non trouvé"
            )
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $regime = $this->repository->findOneBy(['id' => $id]);
        if ($regime) {
            $this->manager->remove($regime);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
    #[Route('', name: 'app_api_regime_list', methods: ['GET'])]
    #[Security("is_granted('ROLE_EMPLOYEE') or is_granted('ROLE_ADMIN')")]
    #[OA\Get(
        path: '/api/regime',
        summary: "Lister tous les régimes",
        description: "Retourne la liste complète des régimes. Accessible uniquement aux employés et administrateurs.",
        tags: ['Regime'],
        security: [['X-AUTH-TOKEN' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des régimes",
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'libelle', type: 'string', example: 'Végétarien'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2026-02-13T14:41:47.389Z')
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
    public function list(RegimeRepository $repo): JsonResponse
    {
        return $this->json($repo->findAll(), Response::HTTP_OK, [], ['groups' => ['regime:read']]);
    }
}