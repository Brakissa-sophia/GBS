<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Unique;

class CategoryForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'attr' => [
                    'placeholder' => 'Saisir le titre de la catégorie'
                ],
                'label' => 'Nom<span class="text-danger">*</span>',
                
                'label_html' => true,
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir le nom de la catégorie'
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Veuillez saisir un nom avec au minimum {{ limit }} caractères',
                        'max' => 30,
                        'maxMessage' => 'Veuillez saisir un titre avec au maximum {{ limit }} caractères'
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
 