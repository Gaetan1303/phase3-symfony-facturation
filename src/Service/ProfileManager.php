<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ProfileManager
 *
 * Responsable de la persistance atomique des modifications du profil utilisateur.
 * Encapsule la logique de transaction pour respecter les propriétés ACID.
 */
final class ProfileManager
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Sauvegarde le profil utilisateur de manière transactionnelle.
     */
    public function saveProfile(User $user): void
    {
        $conn = $this->em->getConnection();
        $conn->beginTransaction();
        try {
            // s'assurer que l'entité est gérée
            $this->em->persist($user);
            // flush dans la transaction
            $this->em->flush();
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }
}
