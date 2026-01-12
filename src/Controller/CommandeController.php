<?php

namespace App\Controller;
use App\Repository\CommandeRepository;
use App\Entity\Commande;
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
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Enum\StatutCommande;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/commande', name: 'app_api_commande_')]
class CommandeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CommandeRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/commande',
        summary: "Créer une commande",
        description: "Crée une commande et retourne la ressource créée. Le header Location pointe vers l’URL de la commande.",
        security: [['X-AUTH-TOKEN' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Données nécessaires à la création d’une commande",
            content: new OA\JsonContent(
                required: [
                    'numero_commande',
                    'date_prestation',
                    'heure_prestation',
                    'prix_menu',
                    'nb_personne',
                    'prix_livraison'
                ],
                properties: [
                    new OA\Property(property: 'numero_commande', type: 'string', example: 'CMD-2026-0001'),
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date',example: '2026-01-20'),
                    new OA\Property(property: 'heure_prestation', type: 'string', format: 'time',example: '12:30:00'),
                    // DECIMAL en Doctrine = string côté PHP, donc type string côté OpenAPI
                    new OA\Property(property: 'prix_menu', type: 'string', example: '15.50'),
                    new OA\Property(property: 'nb_personne', type: 'integer', example: 4),
                    new OA\Property(property: 'prix_livraison', type: 'string',example: '4.90'),
                    new OA\Property(
                        property: 'statut', type: 'string', 
                        description: "Optionnel. Par défaut: en_attente",
                        enum: ['en_attente', 'acceptee', 'preparation', 'livraison', 'livree', 'retour_materiel'],
                        example: 'en_attente'
                    ),
                    new OA\Property(property: 'pret_materiel', type: 'boolean', nullable: true, example: false
                    ),
                    new OA\Property(property: 'restitution_materiel', type: 'boolean', nullable: true, example: false),
                    // date_commande n'est pas requise car auto (PrePersist), mais on la documente comme ignorée si envoyée
                    new OA\Property(
                        property: 'date_commande',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        description: "Optionnel (auto-générée si absente)",
                        example: '2026-01-12T11:00:00+01:00'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Commande créée",
                headers: [
                    new OA\Header(
                        header: 'Location',
                        description: "URL de la commande créée",
                        schema: new OA\Schema(type: 'string', example: 'http://localhost/api/commande/1')
                    )
                ],
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'numero_commande', type: 'string', example: 'CMD-2026-0001'),
                        new OA\Property(
                            property: 'date_commande',
                            type: 'string',
                            format: 'date-time',
                            example: '2026-01-12T11:00:00+01:00'
                        ),
                        new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-01-20'),
                        new OA\Property(property: 'heure_prestation', type: 'string', format: 'time', example: '12:30:00'),
                        new OA\Property(property: 'prix_menu', type: 'string', example: '15.50'),
                        new OA\Property(property: 'nb_personne', type: 'integer', example: 4),
                        new OA\Property(property: 'prix_livraison', type: 'string', example: '4.90'),
                        new OA\Property(property: 'statut', type: 'string', example: 'en_attente'),
                        new OA\Property(property: 'pret_materiel', type: 'boolean', nullable: true, example: false),
                        new OA\Property(property: 'restitution_materiel', type: 'boolean', nullable: true, example: false),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide (JSON invalide / champs manquants / format date-heure incorrect)"
            ),
            new OA\Response(
                response: 401,
                description: "Non authentifié"
            )
        ]
    )]
    public function new(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {   
        $commande = $this->serializer->deserialize($request->getContent(), Commande::class, 'json');
        
        $commande->setUser($user);

        $this->manager->persist($commande);
        $this->manager->flush();

        $responseData = $this->serializer->serialize($commande, 'json', [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object) {
                // On renvoie un identifiant simple pour casser la boucle
                return method_exists($object, 'getId') ? $object->getId() : null;
            },
        ]);

        $location = $this->urlGenerator->generate(
            'app_api_commande_show',
            ['id' => $commande->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return new JsonResponse( $responseData, Response::HTTP_CREATED, ["Location" => $location], true);
    } 
    

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/commande/{id}',
        summary: "Afficher les commandes",
        description: "Retourne la liste des commandes. Par défaut, un utilisateur voit ses commandes. Un employé/admin peut filtrer les commandes.",
        security: [['X-AUTH-TOKEN' => []]],
        parameters: [
            new OA\Parameter(
                name: 'statut',
                in: 'query',
                required: false,
                description: "Filtrer par statut",
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['en_attente', 'accepte', 'preparation', 'livraison', 'livre', 'retour_materiel']
                )
            ),
            new OA\Parameter(
                name: 'userId',
                in: 'query',
                required: false,
                description: "Filtrer par id utilisateur (réservé employé/admin si tu l’imposes)",
                schema: new OA\Schema(type: 'integer', example: 12)
            ),
            new OA\Parameter(
                name: 'datePrestation',
                in: 'query',
                required: false,
                description: "Filtrer par date de prestation (YYYY-MM-DD)",
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-20')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: "Page (à partir de 1)",
                schema: new OA\Schema(type: 'integer', example: 1, default: 1, minimum: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: "Nombre d’éléments par page",
                schema: new OA\Schema(type: 'integer', example: 20, default: 20, minimum: 1, maximum: 100)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Liste des commandes",
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                        new OA\Property(property: 'total', type: 'integer', example: 42),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'numero_commande', type: 'string', example: 'CMD-2026-0001'),
                                    new OA\Property(property: 'date_commande', type: 'string', format: 'date-time', example: '2026-01-12T11:00:00+01:00'),
                                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-01-20'),
                                    new OA\Property(property: 'heure_prestation', type: 'string', format: 'time', example: '12:30:00'),
                                    new OA\Property(property: 'prix_menu', type: 'string', example: '15.50'),
                                    new OA\Property(property: 'nb_personne', type: 'integer', example: 4),
                                    new OA\Property(property: 'prix_livraison', type: 'string', example: '4.90'),
                                    new OA\Property(property: 'statut', type: 'string', example: 'en_attente'),
                                    new OA\Property(property: 'pret_materiel', type: 'boolean', nullable: true, example: false),
                                    new OA\Property(property: 'restitution_materiel', type: 'boolean', nullable: true, example: false),
                                    new OA\Property(
                                        property: 'user',
                                        type: 'object',
                                        description: "Propriétaire de la commande (format léger)",
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 12),
                                            new OA\Property(property: 'email', type: 'string', example: 'client@email.com'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès refusé (ex: user tente de filtrer userId sans droits)"),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            $responseData = $this->serializer->serialize($commande, 'json', [
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function (object $object) {
                    return method_exists($object, 'getId') ? $object->getId() : null;
                },
            ]);

            return new JsonResponse($responseData, Response::HTTP_OK, [], true);
        }

        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    } 

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/commande/{id}',
        summary: "Mettre à jour une commande par ID",
        description: "Règles métier :\n- ROLE_USER (propriétaire) : peut modifier date_prestation, heure_prestation, nb_personne uniquement si statut = en_attente.\n- ROLE_EMPLOYEE : peut modifier uniquement le statut.\n- ROLE_ADMIN : peut modifier tous les champs (hors id, user, date_commande).",
        security: [['X-AUTH-TOKEN' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant de la commande",
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Selon le rôle :\n- USER : envoyer date_prestation, heure_prestation, nb_personne.\n- EMPLOYEE : envoyer statut.\n- ADMIN : envoyer les champs que tu veux modifier.",
            content: new OA\JsonContent(
                type: 'object',
                properties: [
                    // USER fields
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-01-20'),
                    new OA\Property(property: 'heure_prestation', type: 'string', format: 'time', example: '12:30:00'),
                    new OA\Property(property: 'nb_personne', type: 'integer', example: 4),

                    // EMPLOYEE field
                    new OA\Property(
                        property: 'statut',
                        type: 'string',
                        description: "Modifiable par EMPLOYEE/ADMIN. Un USER ne peut pas changer le statut.",
                        enum: ['en_attente', 'accepte', 'en_preparation', 'en_cours_de_livraison', 'livre', 'en_attente_retour_materiel'],
                        example: 'accepte'
                    ),

                    // ADMIN extra fields (si tu autorises vraiment tout)
                    new OA\Property(property: 'numero_commande', type: 'string', example: 'CMD-2026-0001'),
                    new OA\Property(property: 'prix_menu', type: 'string', example: '15.50'),
                    new OA\Property(property: 'prix_livraison', type: 'string', example: '4.90'),
                    new OA\Property(property: 'pret_materiel', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'restitution_materiel', type: 'boolean', nullable: true, example: false),
                ],
                // pas de "required" strict, car le payload dépend du rôle
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Commande mise à jour (aucun contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "JSON invalide / format date-heure incorrect"
            ),
            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),
            new OA\Response(
                response: 403,
                description: "Accès refusé (ex: USER non propriétaire, ou USER tente de modifier alors que statut != en_attente, ou USER tente de changer statut)"
            ),
            new OA\Response(
                response: 404,
                description: "Commande introuvable"
            ),
        ]
    )]

    public function edit(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $commande = $this->repository->find($id);
        if (!$commande) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $isOwner   = $commande->getUser()?->getId() === $user->getId();
        $isAdmin   = $this->isGranted('ROLE_ADMIN');
        $isEmployee = $this->isGranted('ROLE_EMPLOYEE');

        if (!$isOwner && !$isEmployee && !$isAdmin) {
            return new JsonResponse(['message' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        // USER
        if ($isOwner && !$isAdmin && !$isEmployee) {

            if ($commande->getStatut() !== StatutCommande::EN_ATTENTE) {
                return new JsonResponse(
                    ['message' => 'Commande non modifiable après validation'],
                    Response::HTTP_FORBIDDEN
                );
            }

            $this->serializer->deserialize(
                $request->getContent(),
                Commande::class,
                'json',
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $commande,
                    AbstractNormalizer::IGNORED_ATTRIBUTES => [
                        'id',
                        'user',
                        'statut',
                        'prix_menu',
                        'prix_livraison',
                        'pret_materiel',
                        'restitution_materiel',
                        'date_commande',
                    ],
                ]
            );
        }

        // EMPLOYEE
        elseif ($isEmployee && !$isAdmin) {

            $this->serializer->deserialize(
                $request->getContent(),
                Commande::class,
                'json',
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $commande,
                    AbstractNormalizer::IGNORED_ATTRIBUTES => [
                        'id',
                        'user',
                        'date_prestation',
                        'heure_prestation',
                        'nb_personne',
                        'prix_menu',
                        'prix_livraison',
                        'date_commande',
                    ],
                ]
            );
        }

        // ADMIN
        else {
            $this->serializer->deserialize(
                $request->getContent(),
                Commande::class,
                'json',
                [
                    AbstractNormalizer::OBJECT_TO_POPULATE => $commande,
                    AbstractNormalizer::IGNORED_ATTRIBUTES => [
                        'id',
                        'user',
                        'date_commande',
                    ],
                ]
            );
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/commande/{id}',
        summary: "Supprimer une commande par ID",
        description: "Supprime définitivement une commande.\n- ROLE_ADMIN : peut supprimer toute commande.\n- ROLE_EMPLOYEE : peut supprimer selon règles internes.\n- ROLE_USER : peut supprimer uniquement ses propres commandes (souvent uniquement si statut = en_attente).",
        security: [['X-AUTH-TOKEN' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant de la commande à supprimer",
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: "Commande supprimée avec succès (aucun contenu retourné)"
            ),
            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),
            new OA\Response(
                response: 403,
                description: "Accès refusé (droits insuffisants ou commande non supprimable selon son statut)"
            ),
            new OA\Response(
                response: 404,
                description: "Commande introuvable"
            ),
        ]
    )]
    public function delete(int $id): JsonResponse
    {
        $commande = $this->repository->findOneBy(['id' => $id]);
        if ($commande) {
            $this->manager->remove($commande);
            $this->manager->flush();

            return new JsonResponse( null, Response::HTTP_NO_CONTENT);
        }
        
        return new JsonResponse( null, Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}/statut', name: 'patch_statut', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_EMPLOYEE','ROLE_ADMIN')]
    #[OA\Patch(
        path: '/api/commande/{id}/statut',
        summary: "Modifier le statut d'une commande par ID (employé)",
        description: "Permet à un employé (ou admin) de changer le statut d’une commande. Le corps attendu est minimal: { \"statut\": \"...\" }.",
        security: [['X-AUTH-TOKEN' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: "Identifiant de la commande",
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['statut'],
                properties: [
                    new OA\Property(
                        property: 'statut',
                        type: 'string',
                        enum: ['en_attente', 'acceptee', 'refusee', 'preparation', 'livraison', 'livree', 'retour_materiel', 'terminee'],
                        example: 'preparation'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 204,
                description: "Statut mis à jour (aucun contenu retourné)"
            ),
            new OA\Response(
                response: 400,
                description: "Requête invalide (JSON invalide ou statut invalide)"
            ),
            new OA\Response(
                response: 401,
                description: "Non authentifié"
            ),
            new OA\Response(
                response: 403,
                description: "Accès refusé (réservé employé/admin)"
            ),
            new OA\Response(
                response: 404,
                description: "Commande introuvable"
            ),
            new OA\Response(
                response: 409,
                description: "Transition de statut non autorisée"
            ),
        ]
    )]
    public function patchStatut(int $id, Request $request): JsonResponse
    {
        $commande = $this->repository->find($id);
        if (!$commande) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || empty($data['statut']) || !is_string($data['statut'])) {
            return new JsonResponse(['message' => 'Champ requis: statut'], Response::HTTP_BAD_REQUEST);
        }

        $newStatut = StatutCommande::tryFrom($data['statut']);
        if (!$newStatut) {
            return new JsonResponse([
                'message' => 'Statut invalide',
                'allowed' => array_map(fn($c) => $c->value, StatutCommande::cases()),
            ], Response::HTTP_BAD_REQUEST);
        }
        // on n’autorise pas de revenir en arrière
        
        $current = $commande->getStatut();
        $allowedTransitions = [
            StatutCommande::EN_ATTENTE->value => [StatutCommande::ACCEPTEE, StatutCommande::REFUSEE],
            StatutCommande::ACCEPTEE->value => [StatutCommande::PREPARATION],
            StatutCommande::PREPARATION->value => [StatutCommande::LIVRAISON],
            StatutCommande::LIVRAISON->value => [StatutCommande::LIVREE],
            StatutCommande::LIVREE->value => [StatutCommande::RETOUR_MATERIEL],
            StatutCommande::RETOUR_MATERIEL->value => [StatutCommande::TERMINEE],
        ];

        $allowed = $allowedTransitions[$current->value] ?? [];
        if (!in_array($newStatut, $allowed, true)) {
            return new JsonResponse([
                'message' => 'Transition de statut non autorisée',
                'current' => $current->value,
                'allowedNext' => array_map(fn($s) => $s->value, $allowed),
            ], Response::HTTP_CONFLICT);
        }
        

        $commande->setStatut($newStatut);
        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}