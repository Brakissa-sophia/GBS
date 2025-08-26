<?php

namespace App\Form;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Device;
use App\Entity\SkinType;
use App\Repository\CategoryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class DeviceForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'attr' => [
                    'placeholder' => 'Saisir le titre de l\'outil de beauté'
                ],
                'label' => 'Titre<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir le titre de l\'outil de beauté'
                    ]),
                    new Length([
                        'min' => 2,
                        'minMessage' => 'Veuillez saisir un titre avec au minimum {{ limit }} caractères',
                        'max' => 70,
                        'maxMessage' => 'Veuillez saisir un titre avec au maximum {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('description', null, [
                'attr' => [
                    'placeholder' => 'Saisir la description de l\'outil de beauté'
                ],
                'label' => 'Description<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Veuillez saisir une description avec au maximum {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('price', MoneyType::class, [
                'attr' => [
                    'placeholder' => 'Saisir le prix de l\'outil de beauté',
                ],
                'label' => 'Prix<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir le prix de l\'outil de beauté'
                    ]),
                    new Positive([
                        'message' => 'Veuillez saisir un nombre strictement supérieur à zéro'
                    ])
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => function (Category $category) {
                    return $category->getName() . ' (' . $category->getId() . ')';
                },
                'placeholder' => '-- Sélectionner la catégorie d\'outil de beauté --',
                'label' => 'Catégorie<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez sélectionner la catégorie de l\'outil de beauté'
                    ]),
                ],
                'query_builder' => function (CategoryRepository $categoryRepository) {
                    return $categoryRepository->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC');
                }
            ])
            ->add('brand', EntityType::class, [
                'class' => Brand::class,
                'choice_label' => 'title',
                'placeholder' => '-- Sélectionner le nom de la marque --',
                'expanded' => true,
                'multiple' => false,
                'label' => 'Marque',
                'required' => false,
                
            ])

            ->add('skinType', EntityType::class, [
                'class' => SkinType::class,
                'choice_label' => 'title',
                'placeholder' => '-- Sélectionner le type de peau --',
                'expanded' => true,
                'multiple' => true,
                'label' => 'Type de peau',
                'required' => false,
                
            ])
            ->add('ingredients', TextareaType::class, [
                'label' => 'Composants/Matériaux',
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'placeholder' => 'Listez les composants ou matériaux de l\'outil de beauté...',
                    'rows' => 4,
                    'class' => 'form-control'
                ],

            ])

            ->add('usageAdvice', TextareaType::class, [
                'label' => 'Mode d\'emploi',
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'placeholder' => 'Comment utiliser cet outil de beauté...',
                    'rows' => 4,
                    'class' => 'form-control'
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock initial<span class="text-danger">*</span>',
                'label_html' => true,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: 100',
                    'min' => 0,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir la quantité en stock'
                    ]),
                    new PositiveOrZero([
                        'message' => 'Le stock ne peut pas être négatif'
                    ])
                ]
            ])
            ->add('image1', FileType::class, [
                'label' => 'Image principale',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'accept' => 'image/*',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg', 
                            'image/png',
                            'image/webp',
                            'image/avif',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide (JPG, PNG, WEBP)',
                    ])
                ],
            ])
            ->add('image2', FileType::class, [
                'label' => 'Image 2',
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/*', 'class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp','image/avif'],
                        'mimeTypesMessage' => 'Veuillez télécharger une image valide',
                    ])
                ],
            ])
            ->add('image3', FileType::class, [
                'label' => 'Image 3',
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/*', 'class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp','image/avif'],
                    ])
                ],
            ])
            ->add('image4', FileType::class, [
                'label' => 'Image 4',
                'required' => false,
                'mapped' => false,
                'attr' => ['accept' => 'image/*', 'class' => 'form-control'],
                'constraints' => [
                    new File([
                        'maxSize' => '1024k',
                        'mimeTypes' => ['image/jpeg', 'image/jpg', 'image/png', 'image/webp','image/avif'],
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Device::class,
        ]);
    }
}