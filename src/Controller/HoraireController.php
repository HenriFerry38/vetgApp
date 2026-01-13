<?php

namespace App\Controller;
use App\Repository\HoraireRepository;
use App\Entity\Horaire;
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

#[Route('/api/horaire', name: 'app_api_horaire_')]
class HoraireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private HoraireRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/horaire',
        summary: 'Créer un horaire',
        description: 'Crée un nouvel horaire et le persiste en base de données.',
        tags: ['Horaires'],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Données de création de l’horaire',
            content: new OA\JsonContent(
                required: ['jour', 'heureOuverture', 'heureFermeture'],
                properties: [
                    new OA\Property(
                        property: 'jour',
                        type: 'string',
                        example: 'Lundi'
                    ),
                    new OA\Property(
                        property: 'heureOuverture',
                        type: 'string',
                        example: '09:00'
                    ),
                    new OA\Property(
                        property: 'heureFermeture',
                        type: 'string',
                        example: '18:00'
                    ),
                ]
            )
        ),
        
        responses: [
            new OA\Response(
                response: 201,
                description: 'Horaire créé avec succès',
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: 'URL de la ressource créée',
                        schema: new OA\Schema(type: 'string', format: 'uri')
                    )
                ],
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'jour', type: 'string', example: 'Lundi'),
                        new OA\Property(property: 'heureOuverture', type: 'string', example: '09:00'),
                        new OA\Property(property: 'heureFermeture', type: 'string', example: '18:00'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            
            new OA\Response(
                response: 400,
                description: 'Requête invalide'
            )
        ]
    )]
    public function new(Request $request): JsonResponse
    {   
        $horaire = $this->serializer->deserialize($request->getContent(), Horaire::class, 'json');
        $horaire->setCreatedAt(new DateTimeImmutable());
       
        $this->manager->persist($horaire);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($horaire, 'json');
        $location = $this->urlGenerator->generate(
            'app_api_horaire_show',
            ['id' => $horaire->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/horaire/{id}',
        summary: "Afficher un horaire par ID",
        description: "Retourne un horaire à partir de son identifiant",
        tags: ['Horaires'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Identifiant de l’horaire',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Horaire trouvé",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'jour', type: 'string', example: 'Lundi'),
                        new OA\Property(property: 'heureOuverture', type: 'string', example: '09:00'),
                        new OA\Property(property: 'heureFermeture', type: 'string', example: '18:00'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time', nullable: true),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Horaire non trouvé"
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if ($horaire) {
            $responseData = $this->serializer->serialize($horaire, 'json');

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/horaire/{id}',
        summary: "Modifier un horaire par ID",
        description: "Met à jour un horaire existant à partir de son identifiant",
        tags: ['Horaires'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant de l’horaire",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                // si tu veux autoriser le PATCH-like, tu peux enlever required ici
                required: ['jour', 'heureOuverture', 'heureFermeture'],
                properties: [
                    new OA\Property(property: 'jour', type: 'string', example: 'Mardi'),
                    new OA\Property(property: 'heureOuverture', type: 'string', example: '10:00'),
                    new OA\Property(property: 'heureFermeture', type: 'string', example: '19:00'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Horaire modifié (pas de contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide"
            ),
            new OA\Response(
                response: 404,
                description: "Horaire non trouvé"
            )
        ]
    )]
    public function edit(int $id, Request $request): JsonResponse
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if ($horaire) {
            $horaire = $this->serializer->deserialize(
                $request->getContent(),
                Horaire::class,
                'json',
                [AbstractNormalizer::OBJECT_TO_POPULATE => $horaire]
            );
            $horaire->setUpdatedAt(new DateTimeImmutable());
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/horaire/{id}',
        summary: "Supprimer un horaire par ID",
        description: "Supprime un horaire à partir de son identifiant",
        tags: ['Horaires'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant de l’horaire",
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Horaire supprimé avec succès"
            ),
            new OA\Response(
                response: 404,
                description: "Horaire non trouvé"
            )
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $horaire = $this->repository->findOneBy(['id' => $id]);
        if ($horaire) {
            $this->manager->remove($horaire);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }
}

