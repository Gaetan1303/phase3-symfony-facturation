<?php

namespace App\Form;

use App\Entity\Product;
use App\Enum\Unit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Use enum cases directly so the form can map enum instances correctly
        $choices = Unit::cases();

        $builder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('price', MoneyType::class, [
                'currency' => 'EUR',
                'scale' => 2,
                'constraints' => [new NotBlank(), new PositiveOrZero()],
            ])
            ->add('unit', ChoiceType::class, [
                'choices' => $choices,
                'choice_label' => function ($val) { return $val->name; },
                'choice_value' => function ($val) {
                    if ($val instanceof \BackedEnum) {
                        return $val->value;
                    }

                    return $val;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
