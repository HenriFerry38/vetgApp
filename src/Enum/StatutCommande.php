<?php

namespace App\Enum;

enum StatutCommande: string
{
    case EN_ATTENTE = 'en_attente';
    case ACCEPTEE = 'acceptee';
    case REFUSEE = 'refusee';
    case PREPARATION = 'preparation';
    case LIVRAISON = 'livraison';
    case LIVREE = 'livree';
    case RETOUR_MATERIEL = 'retour_materiel';
    case ANNULEE = 'annulee';
    case TERMINEE = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente',
            self::ACCEPTEE => 'Acceptée',
            self::REFUSEE => 'Refusée',
            self::PREPARATION => 'En préparation',
            self::LIVRAISON => 'En cours de livraison',
            self::LIVREE => 'Livrée',
            self::RETOUR_MATERIEL => 'Retour matériel',
            self::ANNULEE => 'Annulée',
            self::TERMINEE => 'terminee',
        };
    }
}