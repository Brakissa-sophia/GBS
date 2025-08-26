<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

           ->add('sexe', ChoiceType::class, [
            'label' => 'Sexe<span class="text-danger">*</span>',
            'label_html' => true,
            'label_attr' => ['class' => 'form-label small fw-medium'],
            'required' => false,
            'placeholder' => false,
            'expanded' => true,        
            'multiple' => false,
            
            'choices' => [
                'Homme' => 'homme',
                'Femme' => 'femme',
                'Non genré' => 'autre',  
            ],
            
            
            'attr' => ['class' => 'd-flex gap-3'], 
            'choice_attr' => ['class' => 'form-check-input'],
            
            'constraints' => [
                new NotBlank([
                    'message' => 'Veuillez sélectionner votre sexe'
                ])
            ]
        ])


            ->add('firstName', TextType::class, [
                'label' => 'Nom<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Votre nom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre nom'
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Veuillez saisir un nom d\'au moins {{ limit }} caractères',
                        'max' => 50,
                        'maxMessage' => 'Veuillez saisir un nom avec au maximum {{ limit }} caractères',
                    ])
                ]
            ])

            ->add('lastName', TextType::class, [
                'label' => 'Prénom<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Votre prénom',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre prénom'
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Veuillez saisir un prénom d\'au moins {{ limit }} caractères',
                        'max' => 50,
                        'maxMessage' => 'Veuillez saisir un prénom avec au maximum {{ limit }} caractères',
                    ])
                ]
            ])

            ->add('email', EmailType::class, [
                'label' => 'Email<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'attr' => [
                    'placeholder' => 'exemple@gbs.com',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Email([
                        'message' => 'Veuillez saisir une adresse mail valide'
                    ]),
                    new NotBlank([
                        'message' => 'Veuillez saisir votre adresse email'
                    ]),
                ]
            ])


            // ->add('plainPassword', PasswordType::class, [
            //    'label' => 'Mot de passe<span class="text-danger">*</span>',
           //     'label_html' => true,
           //     'mapped' => false,
            //    'attr' => ['autocomplete' => 'new-password'],
            //    'required' => false, 
              //  'constraints' => [
               //     new NotBlank([
                 //       'message' => 'Veuillez saisir votre mot de passe',
                  //  ]),
                 //   new Regex([
                   //     'pattern' => '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/',
                //    'match' => true,
                  //      'message' => 'Votre mot de passe doit comporter au moins douze caractères, dont des lettres majuscules et minuscules, un chiffre et un symbole : - + ! * $ @ % _ ? .',
                  //  ]),

               // ],
           // ])

            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les mots de passe doivent être identiques.',
                'options' => [
                    'attr' => ['class' => 'form-control password-field'],
                    'label_html' => true
                ],
                'required' => false,
                'first_options' => [
                    'label' => 'Mot de passe<span class="text-danger">*</span>',
                    'attr' => ['placeholder' => 'Votre mot de passe']
                ],
                'second_options' => [
                    'label' => 'Confirmation Mot de passe<span class="text-danger">*</span>',
                    'attr' => ['placeholder' => 'Confirmez votre mot de passe']
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir votre mot de passe',
                    ]),
                    new Regex([
                        'pattern' => '/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/',
                        'match' => true,
                        'message' => 'Votre mot de passe doit comporter au moins 8 caractères, dont des lettres majuscules et minuscules, un chiffre et un symbole : - + ! * $ @ % _ ? .',
                    ]),
                ]
            ])

            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label_html' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions d\'utilisation.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
   