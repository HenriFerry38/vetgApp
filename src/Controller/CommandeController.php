<?php

namespace App\Controller;
use App\Repository\CommandeRepository;
use App\Repository\MenuRepository;
use App\Entity\Commande;
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
use App\Entity\User;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Enum\StatutCommande;
use Symfony\Component\Security\Http\Attribute\Security;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/api/commande', name: 'app_api_commande_')]
class CommandeController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private CommandeRepository $repository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
        private MenuRepository $menuRepository,
        private MailerInterface $mailer
        )
    {

    }
    #[Route( name: 'new', methods: ['POST'])]
    #[OA\Post(
        path: '/api/commande',
        summary: "Créer une commande",
        description: "Crée une commande et retourne la ressource créée. Le header Location pointe vers l’URL de la commande.",
        tags: ['Commande'],
        security: [['X-AUTH-TOKEN' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Données nécessaires à la création d’une commande",
            content: new OA\JsonContent(
                required: [
                    'menu_id',
                    'adresse_prestation',
                    'date_prestation',
                    'heure_prestation',
                    'nb_personne'
                ],
                properties: [
                    new OA\Property(property: 'menu_id', type: 'int', example: '1'),
                    new OA\Property(property: 'adresse_prestation', type: 'string', example: '1 rues des Gourmands, Bordeaux 33000'), 
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date',example: '2026-01-20'),
                    new OA\Property(property: 'heure_prestation', type: 'string', format: 'time',example: '12:30:00'),
                    new OA\Property(property: 'nb_personne', type: 'integer', example: 4),
                    new OA\Property(
                        property: 'statut', type: 'string', 
                        description: "Optionnel. Par défaut: en_attente",
                        enum: ['en_attente', 'acceptee', 'preparation', 'livraison', 'livree', 'retour_materiel','anulee','terminee'],
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
                        new OA\Property(
                            property: 'date_commande',
                            type: 'string',
                            format: 'date-time',
                            example: '2026-01-12T11:00:00+01:00'
                        ),
                        new OA\Property(property: 'adresse_prestation', type: 'string', example: '1 rues des Gourmands, Bordeaux 33000'),
                        new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-01-20'),
                        new OA\Property(property: 'heure_prestation', type: 'string', format: 'time', example: '12:30:00'),
                        new OA\Property(property: 'nb_personne', type: 'integer', example: 4),
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
        if (!$user) {
            return new JsonResponse(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $menuId = $data['menu_id'] ?? null;
        $nb = (int) ($data['nb_personne'] ?? 0);

        $dateStr = $data['date_prestation'] ?? null;  // "2026-02-10"
        $timeStr = $data['heure_prestation'] ?? null; // "12:30"

        if (!$menuId || $nb <= 0 || !$dateStr || !$timeStr) {
            return new JsonResponse([
                'message' => 'Champs requis: menu_id, nb_personne, date_prestation, heure_prestation'
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->manager->beginTransaction();

        try {

            // 🔒 LOCK pessimiste sur le menu
            $menu = $this->manager->find(
                Menu::class,
                $menuId,
                LockMode::PESSIMISTIC_WRITE
            );

            if (!$menu) {
                $this->manager->rollback();
                return new JsonResponse(['message' => 'Menu introuvable'], Response::HTTP_NOT_FOUND);
            }

            $menu = $this->menuRepository->find($menuId);
            if (!$menu) {
                return new JsonResponse(['message' => 'Menu introuvable'], Response::HTTP_NOT_FOUND);
            }


            // ✅ STOCK: check + décrément
            $stock = (int) ($menu->getQuantiteRestaurant() ?? 0);

            if ($stock <= 0) {
                return new JsonResponse([
                    'message' => 'Rupture de stock',
                    'stock_disponible' => $stock
                ], Response::HTTP_CONFLICT);
            }

            if ($stock < $nb) {
                return new JsonResponse([
                    'message' => 'Stock insuffisant',
                    'stock_disponible' => $stock,
                    'quantite_demandee' => $nb
                ], Response::HTTP_CONFLICT);
            }


            // Dates
            try {
                $datePrestation = new \DateTime($dateStr);
                $heurePrestation = new \DateTime($timeStr);
            } catch (\Throwable $e) {
                return new JsonResponse(['message' => 'Format date/heure invalide'], Response::HTTP_BAD_REQUEST);
            }

            // ✅ prix_commande = prix_par_personne * nb_personne
            $prixParPersonne = (string) $menu->getPrixParPersonne();
            $prixCommande = function_exists('bcmul')
                ? bcmul($prixParPersonne, (string) $nb, 2)
                : number_format(((float)$prixParPersonne) * $nb, 2, '.', '');

            // ✅ prix_livraison (par défaut 0)
            $prixLivraison = $data['prix_livraison'] ?? '0.00';

            // ✅ prix_total = prix_commande + prix_livraison
            $prixTotal = function_exists('bcadd')
                ? bcadd((string)$prixCommande, (string)$prixLivraison, 2)
                : number_format(((float)$prixCommande) + ((float)$prixLivraison), 2, '.', '');
            
            $adresse_prestation = trim((string)($data['adresse_prestation'] ?? ''));
            if ($adresse_prestation === '') {
                return new JsonResponse(['message' => 'Champs requis: adresse_prestation'], Response::HTTP_BAD_REQUEST);
            }

            $commande = new Commande();
            $commande->setNumeroCommande(date('ymdHis') . random_int(10, 99));
            $commande->setUser($user);
            $commande->setMenu($menu);
            $commande->setAdressePrestation((string)$adresse_prestation);

            $commande->setNbPersonne($nb);
            $commande->setDatePrestation($datePrestation);
            $commande->setHeurePrestation($heurePrestation);

            $commande->setPrixCommande((string)$prixCommande);
            $commande->setPrixLivraison((string)$prixLivraison);
            $commande->setPrixTotal((string)$prixTotal);

            $menu->setQuantiteRestaurant($stock - $nb);

            $this->manager->persist($commande);
            $this->manager->flush();
            $this->manager->commit();

            return new JsonResponse(
                $this->serializer->serialize($commande, 'json', [
                    AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => fn($o) => method_exists($o, 'getId') ? $o->getId() : null
                ]),
                Response::HTTP_CREATED,
                [],
                true
            );
        } catch (\Throwable $e) {
             $this->manager->rollback();

            return new JsonResponse([
                'message' => 'Erreur serveur',
                'detail' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/commande/{id}',
        summary: "Afficher les commandes",
        description: "Retourne la liste des commandes. Par défaut, un utilisateur voit ses commandes. Un employé/admin peut filtrer les commandes.",
        tags: ['Commande'],
        security: [['X-AUTH-TOKEN' => []]],
        parameters: [
            new OA\Parameter(
                name: 'statut',
                in: 'query',
                required: false,
                description: "Filtrer par statut",
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['en_attente', 'acceptee', 'preparation', 'livraison', 'livree', 'retour_materiel','anulee','terminee']
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
                                    new OA\Property(property: 'adresse_prestation',type: 'string', example: '1 rue des Gourmands, Bordeaux 33000'),
                                    new OA\Property(property: 'date_commande', type: 'string', format: 'date-time', example: '2026-01-12T11:00:00+01:00'),
                                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-01-20'),
                                    new OA\Property(property: 'heure_prestation', type: 'string', format: 'time', example: '12:30:00'),
                                    new OA\Property(property: 'prix_commande', type: 'string', example: '15.50'),
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

    #[Route('/{id}', name: 'edit', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[OA\Put(
        path: '/api/commande/{id}',
        summary: "Mettre à jour une commande par ID",
        description: "Règles métier :\n- ROLE_USER (propriétaire) : peut modifier adresse_prestation, date_prestation, heure_prestation, nb_personne uniquement si statut = en_attente.\n- ROLE_EMPLOYEE : peut modifier uniquement le statut.\n- ROLE_ADMIN : peut modifier tous les champs (hors id, user, date_commande).",
        tags: ['Commande'],
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
                    new OA\Property(property: 'adresse_prestation',type: 'string', example: '1 rue des Animaux, Bordeaux 33000'),
                    new OA\Property(property: 'date_prestation', type: 'string', format: 'date', example: '2026-01-20'),
                    new OA\Property(property: 'heure_prestation', type: 'string', format: 'time', example: '12:30:00'),
                    new OA\Property(property: 'nb_personne', type: 'integer', example: 4),

                    // EMPLOYEE field
                    new OA\Property(
                        property: 'statut',
                        type: 'string',
                        description: "Modifiable par EMPLOYEE/ADMIN. Un USER ne peut pas changer le statut.",
                        enum: ['en_attente', 'acceptee', 'en_preparation', 'en_cours_de_livraison', 'livre', 'retour_materiel','anulee','terminee'],
                        example: 'acceptee'
                    ),

                    // ADMIN extra fields (si tu autorises vraiment tout)
                    new OA\Property(property: 'numero_commande', type: 'string', example: 'CMD-2026-0001'),
                    new OA\Property(property: 'prix_commande', type: 'string', example: '15.50'),
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
                    ['message' => 'Commande non modifiable après validation, veuillez nous contacter.'],
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
                        'prix_commande',
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
                        'prix_commande',
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

    
    #[Route('/{id}', name: 'delete', methods: ['DELETE'],requirements: ['id' => '\d+'])]
    #[OA\Delete(
        path: '/api/commande/{id}',
        summary: "Supprimer une commande par ID",
        description: "Supprime définitivement une commande.\n- ROLE_ADMIN : peut supprimer toute commande.\n- ROLE_EMPLOYEE : peut supprimer selon règles internes.\n- ROLE_USER : peut supprimer uniquement ses propres commandes (souvent uniquement si statut = en_attente).",
        tags: ['Commande'],
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
    #[Security("is_granted('ROLE_EMPLOYEE') or is_granted('ROLE_ADMIN')")]
    #[OA\Patch(
        path: '/api/commande/{id}/statut',
        summary: "Modifier le statut d'une commande par ID (employé)",
        description: "Permet à un employé (ou admin) de changer le statut d’une commande. Le corps attendu est minimal: { \"statut\": \"...\" }.",
        tags: ['Employé'],
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
                        enum: ['en_attente', 'acceptee', 'refusee', 'preparation', 'livraison', 'livree', 'retour_materiel', 'annulee', 'terminee'],
                        example: 'acceptee'
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

        $pretMateriel = (bool) ($commande->getMenu()?->isPretMateriel());

        if ($newStatut === StatutCommande::TERMINEE && $pretMateriel && !$commande->isRestitutionMateriel()) {
            return new JsonResponse([
                'message' => "Impossible de terminer: restitution matériel non confirmée."
            ], Response::HTTP_CONFLICT);
        }


        $current = $commande->getStatut();

        // transitions "par défaut"
        $allowedTransitions = [
            StatutCommande::EN_ATTENTE->value => [StatutCommande::ACCEPTEE, StatutCommande::REFUSEE],
            StatutCommande::ACCEPTEE->value => [StatutCommande::PREPARATION],
            StatutCommande::PREPARATION->value => [StatutCommande::LIVRAISON],
            StatutCommande::LIVRAISON->value => [StatutCommande::LIVREE],
            StatutCommande::RETOUR_MATERIEL->value => [StatutCommande::TERMINEE],
        ];

        $allowed = $allowedTransitions[$current->value] ?? [];

        // Cas métier: après LIVREE -> TERMINEE si pas de prêt matériel, sinon RETOUR_MATERIEL
        if ($current === StatutCommande::LIVREE) {
            $allowed = $pretMateriel
                ? [StatutCommande::RETOUR_MATERIEL]
                : [StatutCommande::TERMINEE];
        }

        if (!in_array($newStatut, $allowed, true)) {
            return new JsonResponse([
                'message' => 'Transition de statut non autorisée',
                'current' => $current->value,
                'allowedNext' => array_map(fn($s) => $s->value, $allowed),
            ], Response::HTTP_CONFLICT);
        }

        // 🔒 IMPORTANT: ne pas permettre "annulee" via PATCH statut si tu forces ton endpoint annulation
        if ($newStatut === StatutCommande::ANNULEE) {
            return new JsonResponse([
                'message' => "Annulation interdite via /statut. Utilisez l'endpoint d'annulation avec mode_contact + motif."
            ], Response::HTTP_FORBIDDEN);
        }

        $commande->setStatut($newStatut);

        // ✅ Hook: passage à RETOUR_MATERIEL => date + mail
        if ($newStatut === StatutCommande::RETOUR_MATERIEL) {

            if ($commande->getRetourMaterielAt() === null) {
                $commande->setRetourMaterielAt(new \DateTimeImmutable());
            }

            $emailClient = $commande->getUser()?->getEmail();
            if ($emailClient) {
                $mail = (new Email())
                    ->from('contact@viteetgourmand.fr')
                    ->to($emailClient)
                    ->subject('Retour de matériel: délai de 10 jours ouvrés')
                    ->text(
                        "Bonjour,\n\n"
                        ."Votre commande ".$commande->getNumeroCommande()." est passée au statut \"En attente du retour de matériel\".\n"
                        ."Vous disposez de 10 jours ouvrés pour restituer le matériel.\n"
                        ."Sans restitution dans ce délai, des frais de 600 euros seront appliqués (voir CGV).\n\n"
                        ."Pour rendre le matériel, merci de prendre contact avec la société.\n\n"
                        ."Cordialement,\nVite & Gourmand"
                    );

                $mailer->send($mail);
            }
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    
    #[Route('/{id}/annulation', name: 'patch_annulation', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[Security("is_granted('ROLE_EMPLOYEE') or is_granted('ROLE_ADMIN')")]
    #[OA\Patch(
        path: '/api/commande/{id}/annulation',
        summary: "Annuler / refuser une commande (avec motif et mode de contact)",
        description: "Règle métier: un employé/admin ne peut annuler/refuser qu'après contact client. Champs obligatoires: mode_contact (gsm|mail) + motif. Envoie un mail au client (si email présent).",
        tags: ['Employé'],
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
                required: ['mode_contact', 'motif'],
                properties: [
                    new OA\Property(property: 'mode_contact', type: 'string', enum: ['gsm', 'mail'], example: 'mail'),
                    new OA\Property(property: 'motif', type: 'string', example: 'Client injoignable / rupture stock / report impossible'),
                    new OA\Property(
                        property: 'type',
                        type: 'string',
                        enum: ['annulee', 'refusee'],
                        example: 'annulee',
                        description: "Optionnel. Par défaut: annulee."
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: "Commande annulée/refusée (aucun contenu)"),
            new OA\Response(response: 400, description: "Champs manquants ou invalides"),
            new OA\Response(response: 401, description: "Non authentifié"),
            new OA\Response(response: 403, description: "Accès refusé"),
            new OA\Response(response: 404, description: "Commande introuvable"),
        ]
    )]
    public function patchAnnulation(int $id, Request $request): JsonResponse
    {
        $commande = $this->repository->find($id);
        if (!$commande) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        $mode = $data['mode_contact'] ?? null;
        $motif = trim((string)($data['motif'] ?? ''));
        $type = $data['type'] ?? 'annulee';

        if (!in_array($mode, ['gsm', 'mail'], true) || $motif === '') {
            return new JsonResponse(['message' => 'Champs requis: mode_contact (gsm|mail), motif'], Response::HTTP_BAD_REQUEST);
        }

        // Optionnel: interdire l’annulation/refus si déjà terminée
        if ($commande->getStatut() === StatutCommande::TERMINEE) {
            return new JsonResponse(['message' => "Impossible: commande déjà terminée."], Response::HTTP_CONFLICT);
        }

        $newStatut = match ($type) {
            'refusee' => StatutCommande::REFUSEE,
            default => StatutCommande::ANNULEE,
        };

        $current = $commande->getStatut();
        if (in_array($commande->getStatut(), [StatutCommande::ANNULEE, StatutCommande::REFUSEE], true)) {
            return new JsonResponse(['message' => 'Commande déjà annulée/refusée.'], Response::HTTP_CONFLICT);
        }

        $commande->setAnnulationModeContact($mode);
        $commande->setAnnulationMotif($motif);
        $commande->setAnnuleeAt(new \DateTimeImmutable());
        $commande->setStatut($newStatut);

        $menu = $commande->getMenu();
        if ($menu && $menu->getQuantiteRestaurant() !== null) {
            $stock = (int) $menu->getQuantiteRestaurant();
            $nb = (int) ($commande->getNbPersonne() ?? 0);

            $menu->setQuantiteRestaurant($stock + $nb);
        }
        // Mail au client (si email existe)
        $emailClient = $commande->getUser()?->getEmail();
        if ($emailClient) {
            $subject = $newStatut === StatutCommande::REFUSEE
                ? 'Votre commande a été refusée'
                : 'Votre commande a été annulée';
            $introContact = match ($mode) {
                'gsm'  => "Suite à notre échange téléphonique,",
                'mail' => "Suite à nos échanges par email,",
                default => "Suite à nos échanges,",
            };
            
            $modeLabel = $mode === 'gsm' ? 'Téléphone' : 'Email';


            $mail = (new Email())
                ->from('contact@viteetgourmand.fr')
                ->to($emailClient)
                ->subject($subject)
                ->text(
                     "Bonjour,\n\n"
                    .$introContact." nous vous informons concernant votre commande ".$commande->getNumeroCommande()." :\n\n"
                    .($newStatut === StatutCommande::REFUSEE ? "Statut : Refusée\n" : "Statut : Annulée\n")
                    ."Mode de contact : ".$modeLabel."\n"
                    ."Motif : ".$motif."\n\n"
                    ."Si vous souhaitez reprogrammer une prestation, répondez à ce mail.\n\n"
                    ."Cordialement,\nVite & Gourmand"
                );

            // Si Mailpit est OFF, tu peux éviter de casser l’API:
            try {
                $this->mailer->send($mail);
            } catch (\Throwable $e) {
                // Option: logger $e->getMessage()
            }
        }

        $this->manager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/historique', name: 'account_historique', methods: ['GET'])]
    public function myCommandes(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return new JsonResponse(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $commandes = $this->repository->findBy(
            ['user' => $user],
            ['date_prestation' => 'DESC']
        );

        $out = array_map(static function (Commande $c) {
            $menu = $c->getMenu();

            return [
                'id' => $c->getId(),
                'numero_commande' => $c->getNumeroCommande(), // <- via getter
                'date_prestation' => $c->getDatePrestation()?->format('d/m/Y'),
                'heure_prestation' => $c->getHeurePrestation()?->format('H:i'),
                'nb_personne' => $c->getNbPersonne(),
                'prix_total' => $c->getPrixTotal(),
                'statut' => $c->getStatut()?->value ?? null, // si enum backed
                'menu' => $menu ? [
                    'id' => $menu->getId(),
                    'titre' => $menu->getTitre(),
                ] : null,
            ];
        }, $commandes);

        return new JsonResponse($out, Response::HTTP_OK);
    }
}