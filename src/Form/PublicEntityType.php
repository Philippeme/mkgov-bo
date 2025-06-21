<?php

namespace App\Form;

use App\Entity\Department;
use App\Entity\PublicEntity;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PublicEntityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('institutionName', TextType::class, [
                'label' => 'Institution Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter institution name'
                ]
            ])
            ->add('department', EntityType::class, [
                'class' => Department::class,
                'choice_label' => 'name',
                'label' => 'Department',
                'placeholder' => 'Select a department',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('d')
                        ->where('d.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('d.displayOrder', 'ASC')
                        ->addOrderBy('d.name', 'ASC');
                }
            ])
            ->add('headquartersAddress', TextareaType::class, [
                'label' => 'Headquarters Address',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Enter headquarters address'
                ]
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+237 XXX XXX XXX'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'institution@domain.com'
                ]
            ])
            ->add('website', UrlType::class, [
                'label' => 'Website URL',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://www.example.com'
                ]
            ])
            ->add('contactPersonName', TextType::class, [
                'label' => 'Contact Person Name',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter contact person full name'
                ]
            ])
            ->add('contactPersonEmail', EmailType::class, [
                'label' => 'Contact Person Email',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'contact@domain.com'
                ]
            ])
            ->add('contactPersonPhone', TelType::class, [
                'label' => 'Contact Person Phone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '+237 XXX XXX XXX'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Enter institution description'
                ]
            ])
            ->add('code', TextType::class, [
                'label' => 'Institution Code',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter institution code'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    'Pending' => 'pending',
                    'Suspended' => 'suspended'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('logoFile', FileType::class, [
                'label' => 'Institution Logo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/svg+xml',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, SVG, WebP)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
            ->add('removeLogo', CheckboxType::class, [
                'label' => 'Remove current logo',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'data' => $options['data']->getDisplayOrder() ?: 0
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'data' => $options['data']->isActive() !== false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PublicEntity::class,
        ]);
    }
}