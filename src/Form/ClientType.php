<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du client',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom du client est obligatoire.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => ['class' => 'block w-full rounded-md border border-gray-200 h-11 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'constraints' => [
                    new Assert\Email(message: 'Veuillez entrer une adresse email valide.'),
                ],
                'attr' => ['class' => 'block w-full rounded-md border border-gray-200 h-11 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500']
            ])
            ->add('phone', TextType::class, [
                'label' => 'Numéro de téléphone',
                'required' => false,
                'attr' => ['class' => 'block w-full rounded-md border border-gray-200 h-11 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500']
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['class' => 'block w-full rounded-md border border-gray-200 h-11 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500']
            ])
            ->add('siret', TextType::class, [
                'label' => 'SIRET (optionnel)',
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 20),
                ],
                'attr' => ['class' => 'block w-full rounded-md border border-gray-200 h-11 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500']
            ])
            ->add('rib', TextType::class, [
                'label' => 'RIB',
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: 34),
                ],
                'attr' => ['class' => 'block w-full rounded-md border border-gray-200 h-11 px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'client_type';
    }
}
