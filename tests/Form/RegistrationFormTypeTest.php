<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\Validation;

/**
 * Test unitaire du formulaire d'inscription.
 *
 * Ce test vérifie :
 * - que la soumission de données valides remplit correctement l'objet `User` associé au formulaire;
 * - que le champ `plainPassword` n'est pas mappé sur l'entité (gestion du mot de passe en clair → hachage séparé).
 *
 * Remarques sur les choix techniques :
 * - On étend `TypeTestCase` pour tester uniquement le formulaire (sans boot du kernel).
 * - La méthode `getExtensions()` renvoie `ValidatorExtension` pour activer les contraintes de validation
 *   (ex : `NotBlank`, `Email`) lors de la soumission du formulaire en test.
 */
class RegistrationFormTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        // Données simulées envoyées par l'utilisateur via le formulaire
        $formData = [
            'email' => 'bob@example.com',
            'plainPassword' => 'password123',
            'firstName' => 'Bob',
            'lastName' => 'Martin',
            'companyName' => 'BobCo',
            'iban' => 'FR7630006000011234567890189',
            'siret' => '98765432100012',
        ];

        $model = new User();

        // Création du form type basé sur l'entité modèle
        $form = $this->factory->create(RegistrationFormType::class, $model);

        // Soumission des données (simulation côté serveur)
        $form->submit($formData);

        // Le form doit être synchronisé avec l'objet cible
        $this->assertTrue($form->isSynchronized());

        $user = $form->getData();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('bob@example.com', $user->getEmail());
        $this->assertSame('Bob', $user->getFirstName());
        $this->assertSame('Martin', $user->getLastName());
        $this->assertSame('BobCo', $user->getCompanyName());
        $this->assertSame('FR7630006000011234567890189', $user->getIban());
        $this->assertSame('98765432100012', $user->getSiret());

        // plainPassword n'est pas mappé : le mot de passe en clair doit être traité/haché séparément
        $this->assertFalse($form->get('plainPassword')->getConfig()->getMapped());
    }

    protected function getExtensions(): array
    {
        // Fournit l'extension de validation au TestCase pour que les contraintes
        // ajoutées dans le FormType soient évaluées pendant le test.
        $validator = Validation::createValidator();
        return [new ValidatorExtension($validator)];
    }
}
