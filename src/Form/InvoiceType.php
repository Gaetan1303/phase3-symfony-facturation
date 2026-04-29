<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Invoice;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'] ?? null;

        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir un client',
                'query_builder' => function (EntityRepository $er) use ($user) {
                    return $er->createQueryBuilder('c')
                        ->andWhere('c.user = :user')
                        ->setParameter('user', $user)
                        ->orderBy('c.name', 'ASC');
                },
                'required' => false,
            ])
            ->add('createdAt', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
            ])
            ->add('lines', CollectionType::class, [
                'entry_type' => InvoiceLineType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('save_draft', SubmitType::class, [ 'label' => 'Enregistrer (brouillon)'])
            ->add('validate', SubmitType::class, [ 'label' => 'Valider la facture'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
            'user' => null,
        ]);
    }
}
