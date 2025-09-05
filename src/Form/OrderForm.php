<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Types
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;

// Contraintes
use Symfony\Component\Validator\Constraints as Assert;

class OrderForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Prénom
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'given-name',
                    'placeholder'  => 'Ex. Fatou',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'Le prénom est obligatoire.'),
                    new Assert\Length(min: 2, max: 100),
                    new Assert\Regex(
                        pattern: '/^[\p{L}][\p{L}\p{M}\s\'\-]+$/u',
                        message: 'Le prénom contient des caractères invalides.'
                    ),
                ],
                'empty_data' => '',
            ])

            // Nom
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'family-name',
                    'placeholder'  => 'Ex. Ndiaye',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'Le nom est obligatoire.'),
                    new Assert\Length(min: 2, max: 100),
                    new Assert\Regex(
                        pattern: '/^[\p{L}][\p{L}\p{M}\s\'\-]+$/u',
                        message: 'Le nom contient des caractères invalides.'
                    ),
                ],
                'empty_data' => '',
            ])

            // Email
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder'  => 'exemple@domaine.com',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'L’email est obligatoire.'),
                    new Assert\Email(message: 'Format d’email invalide.'),
                    new Assert\Length(max: 180),
                ],
                'empty_data' => '',
            ])

            // Téléphone
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'tel',
                    'placeholder'  => '06 12 34 56 78',
                    'inputmode'    => 'tel',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'Le téléphone est obligatoire.'),
                    // Autorise chiffres, espaces, +, (), tirets — taille 7 à 20
                    new Assert\Regex(
                        pattern: '/^\+?[0-9\s().\-]{7,20}$/',
                        message: 'Le format du téléphone est invalide.'
                    ),
                ],
                'empty_data' => '',
            ])

            // Rue
            ->add('street', TextType::class, [
                'label' => 'Rue et numéro',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'address-line1',
                    'placeholder'  => '12 rue de la Paix',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'L’adresse est obligatoire.'),
                    new Assert\Length(min: 3, max: 255),
                ],
                'empty_data' => '',
            ])

            // Code postal (FR)
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'required' => false,
                'attr' => [
                    'inputmode'    => 'numeric',
                    'autocomplete' => 'postal-code',
                    'pattern'      => '\d{5}',
                    'maxlength'    => 5,
                    'placeholder'  => '75001',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'Le code postal est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^\d{5}$/',
                        message: 'Le code postal doit comporter 5 chiffres (FR).'
                    ),
                ],
                'empty_data' => '',
            ])

            // Ville
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => [
                    'autocomplete' => 'address-level2',
                    'placeholder'  => 'Paris',
                ],
                'constraints' => [
                    new Assert\NotBlank(normalizer: 'trim', message: 'La ville est obligatoire.'),
                    new Assert\Length(min: 2, max: 100),
                    new Assert\Regex(
                        pattern: '/^[\p{L}][\p{L}\p{M}\s\'\-]+$/u',
                        message: 'La ville contient des caractères invalides.'
                    ),
                ],
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'allow_extra_fields' => false,
            'csrf_protection' => true,
        ]);
    }
}
