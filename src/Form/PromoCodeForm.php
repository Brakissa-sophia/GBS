<?php

namespace App\Form;

use App\Entity\PromoCode;
use App\Entity\Category;
use App\Entity\Brand;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PromoCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code promo',
                'required' => true,
                'attr' => [
                    'class' => 'form-control text-uppercase',
                    'placeholder' => 'Ex: GLOW20, BIENVENUE10',
                    'maxlength' => 50
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le code promo est obligatoire.']),
                    new Assert\Length([
                        'max' => 50,
                        'maxMessage' => 'Le code ne peut pas dépasser {{ limit }} caractères.'
                    ])
                ]
            ])
            ->add('discountType', ChoiceType::class, [
                'label' => 'Type de réduction',
                'choices' => [
                    'Pourcentage (%)' => 'percentage',
                    'Montant fixe (€)' => 'fixed'
                ],
                'required' => true,
                'attr' => ['class' => 'form-select']
            ])
            ->add('discountValue', NumberType::class, [
                'label' => 'Valeur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 20 ou 10.00',
                    'step' => '0.01',
                    'min' => '0.01'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La valeur est obligatoire.']),
                    new Assert\Positive(['message' => 'La valeur doit être supérieure à 0.'])
                ]
            ])
            ->add('maxUsesPerUser', IntegerType::class, [
                'label' => 'Nombre max d\'utilisations par utilisateur',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 1=usage unique',
                    'min' => '1'
                ],
                'help' => 'Laissez vide pour illimité. ',
                'constraints' => [
                    new Assert\Positive(['message' => 'Le nombre doit être supérieur à 0.'])
                ]
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date de fin',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'help' => 'Laissez vide pour pas de limite'
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '-- Toutes les catégories --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Laissez vide pour appliquer à tout le site'
            ])
            ->add('brand', EntityType::class, [
                'label' => 'Marque',
                'class' => Brand::class,
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => '-- Toutes les marques --',
                'attr' => ['class' => 'form-select'],
                'help' => 'Laissez vide pour appliquer à toutes les marques'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Code actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PromoCode::class,
        ]);
    }
}