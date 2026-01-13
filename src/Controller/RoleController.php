<?php

namespace App\Controller;
use App\Repository\RoleRepository;
use App\Entity\Role;
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

#[Route('/api/role', name: 'app_api_role_')]
class RoleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private RoleRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/role',
        summary: "Créer un nouveau rôle",
        description: "Crée un rôle et retourne la ressource créée",
        tags: ['Role'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'libelle'],
                properties: [
                    new OA\Property(
                        property: 'code',
                        type: 'string',
                        example: 'ROLE_EMPLOYEE'
                    ),
                    new OA\Property(
                        property: 'libelle',
                        type: 'string',
                        example: 'Employé'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Rôle créé",
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
                        new OA\Property(property: 'code', type: 'string', example: 'ROLE_EMPLOYEE'),
                        new OA\Property(property: 'libelle', type: 'string', example: 'Employé'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide"
            ),
            new OA\Response(
                response: 409,
                description: "Conflit (code déjà existant)"
            )
        ]
    )]
    public function new(Request $request): JsonResponse
    {   
        $role = $this->serializer->deserialize($request->getContent(), Role::class, 'json');
        $role->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($role);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($role, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_role_show',
            ['id' => $role->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/role/{id}',
        summary: "Afficher un rôle par ID",
        description: "Retourne un rôle à partir de son identifiant",
        tags: ['Role'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du rôle",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Rôle trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'code', type: 'string', example: 'ROLE_EMPLOYEE'),
                        new OA\Property(property: 'libelle', type: 'string', example: 'Employé'),
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
                description: "Rôle non trouvé"
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if ($role) {
            $responseData = $this->serializer->serialize($role, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/role/{id}',
        summary: "Modifier un rôle par ID",
        description: "Met à jour un rôle existant à partir de son identifiant",
        tags: ['Role'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du rôle",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'libelle'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'ROLE_ADMIN'),
                    new OA\Property(property: 'libelle', type: 'string', example: 'Administrateur'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Rôle modifié avec succès (aucun contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide"
            ),
            new OA\Response(
                response: 404,
                description: "Rôle non trouvé"
            ),
            new OA\Response(
                response: 409,
                description: "Conflit (code déjà existant)"
            )
        ]
    )]
        public function edit(int $id, Request $request): JsonResponse
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if ($role) {
            $role = $this->serializer->deserialize(
                $request->getContent(),
                Role::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $role]
            );
            $role->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);

    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/role/{id}',
        summary: "Supprimer un rôle par ID",
        description: "Supprime un rôle à partir de son identifiant",
        tags: ['Role'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du rôle",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Rôle supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Rôle non trouvé"
            )
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $role = $this->repository->findOneBy(['id' => $id]);
        if ($role) {
            $this->manager->remove($role);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}