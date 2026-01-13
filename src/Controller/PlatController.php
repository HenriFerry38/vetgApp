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
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/plat',
        summary: "Créer un nouveau plat",
        description: "Crée un plat et retourne la ressource créée",
        tags: ['Plat'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['titre'],
                properties: [
                    new OA\Property(
                        property: 'titre',
                        type: 'string',
                        example: 'Poulet rôti'
                    ),
                    new OA\Property(
                        property: 'photo',
                        type: 'string',
                        nullable: true,
                        example: 'https://exemple.com/images/poulet-roti.jpg'
                    ),
                    // Si ton entité Plat contient d'autres champs (description, prix, etc.),
                    // on les ajoutera ici pour coller au modèle réel.
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Plat créé",
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
                        new OA\Property(property: 'titre', type: 'string', example: 'Poulet rôti'),
                        new OA\Property(property: 'photo', type: 'string', nullable: true, example: 'https://exemple.com/images/poulet-roti.jpg'),
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
        $plat = $this->serializer->deserialize($request->getContent(), Plat::class, 'json');
        $plat->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($plat);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($plat, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_plat_show',
            ['id' => $plat->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/plat/{id}',
        summary: "Afficher un plat par ID",
        description: "Retourne un plat à partir de son identifiant",
        tags: ['Plat'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du plat",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Plat trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'titre', type: 'string', example: 'Poulet rôti'),
                        new OA\Property(
                            property: 'photo',
                            type: 'string',
                            nullable: true,
                            example: 'https://exemple.com/images/poulet-roti.jpg'
                        ),
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
                description: "Plat non trouvé"
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $responseData = $this->serializer->serialize($plat, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/plat/{id}',
        summary: "Modifier un plat par ID",
        description: "Met à jour un plat existant à partir de son identifiant",
        tags: ['Plat'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du plat",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                // PUT “souple” : tu peux laisser tout optionnel si ton controller le permet
                properties: [
                    new OA\Property(property: 'titre', type: 'string', example: 'Poulet rôti (version XL)'),
                    new OA\Property(
                        property: 'photo',
                        type: 'string',
                        nullable: true,
                        example: 'https://exemple.com/images/poulet-roti-xl.jpg'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Plat modifié (pas de contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide"
            ),
            new OA\Response(
                response: 404,
                description: "Plat non trouvé"
            )
        ]
    )]
    public function edit(int $id, Request $request): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $plat = $this->serializer->deserialize(
                $request->getContent(),
                Plat::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $plat]
            );
            $plat->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/plat/{id}',
        summary: "Supprimer un plat par ID",
        description: "Supprime un plat à partir de son identifiant",
        tags: ['Plat'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant du plat",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Plat supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Plat non trouvé"
            )
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $plat = $this->repository->findOneBy(['id' => $id]);
        if ($plat) {
            $this->manager->remove($plat);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}