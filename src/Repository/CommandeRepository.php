<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function searchForEmployee(
        ?string $statut,
        ?string $q,
        ?int $userId,
        ?string $datePrestation,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')->addSelect('u')
            ->leftJoin('c.menu', 'm')->addSelect('m')
            ->orderBy('c.date_commande', 'DESC');

        if ($statut) {
            // si statut est enum, adapte selon ton mapping
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        if ($userId) {
            $qb->andWhere('u.id = :uid')->setParameter('uid', $userId);
        }

        if ($datePrestation) {
            $qb->andWhere('c.date_prestation = :dp')->setParameter('dp', $datePrestation);
        }

        if ($q) {
            $qLike = '%' . mb_strtolower(trim($q)) . '%';
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.nom) LIKE :q OR LOWER(u.prenom) LIKE :q OR LOWER(u.telephone) LIKE :q')
            ->setParameter('q', $qLike);
        }

        // total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        // pagination
        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        // mapping (format attendu par ton OA)
        $mapped = array_map(static function ($c) {
            $u = $c->getUser();
            $m = $c->getMenu();

            return [
                'id' => $c->getId(),
                'numero_commande' => $c->getNumeroCommande(),
                'adresse_prestation' => $c->getAdressePrestation(),
                'date_commande' => $c->getDateCommande()?->format(DATE_ATOM),
                'date_prestation' => $c->getDatePrestation()?->format('Y-m-d'),
                'heure_prestation' => $c->getHeurePrestation()?->format('H:i:s'),
                'prix_commande' => (string) $c->getPrixTotal(), // ou prix menu + livraison selon ton modèle
                'nb_personne' => $c->getNbPersonne(),
                'prix_livraison' => (string) ($c->getPrixLivraison() ?? '0.00'),
                'statut' => $c->getStatut()?->value ?? $c->getStatut(), // enum backed ou string
                'pret_materiel' => $m ? (bool) $m->isPretMateriel() : false,
                'restitution_materiel' => (bool) ($c->isRestitutionMateriel() ?? false),
                'user' => $u ? [
                    'id' => $u->getId(),
                    'email' => $u->getEmail(),
                    'nom' => $u->getNom(),
                    'prenom' => $u->getPrenom(),
                    'telephone' => $u->getTelephone(),
                ] : null,
                'menu' => $m ? [
                    'id' => $m->getId(),
                    'titre' => $m->getTitre(),
                ] : null,
            ];
        }, $items);

        return [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'items' => $mapped,
        ];
    }
}
