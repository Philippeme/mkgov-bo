<?php

namespace App\Form;

use App\Entity\Role;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter username'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Username is required']),
                    new Length(['min' => 3, 'max' => 100])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter email address'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter first name'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter last name'
                ]
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter phone number'
                ]
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Avatar',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WEBP, GIF)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
            ->add('userRoles', EntityType::class, [
                'class' => Role::class,
                'choice_label' => 'displayName',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('r')
                        ->where('r.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('r.displayOrder', 'ASC')
                        ->addOrderBy('r.name', 'ASC');
                },
                'attr' => [
                    'class' => 'roles-checkboxes'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active User',
                'required' => false,
                'data' => true, // Par défaut actif
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Email Verified',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'data' => 0,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ]
            ]);

        // Add password field based on context
        if (!$isEdit) {
            // For new users, password is required
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter a password'
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096
                    ]),
                ],
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Enter password'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Confirm password'
                    ]
                ],
                'invalid_message' => 'The password fields must match.',
            ]);
        } else {
            // For editing users, password is optional
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
                'first_options' => [
                    'label' => 'New Password (leave blank to keep current)',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Enter new password'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Confirm new password'
                    ]
                ],
                'invalid_message' => 'The password fields must match.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}