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
                'help' => 'Must be unique and contain only letters, numbers, dots, underscores and hyphens',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter unique username',
                    'autocomplete' => 'username'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Username is required']),
                    new Length(['min' => 3, 'max' => 100])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'help' => 'A valid email address that will be used for notifications',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'user@example.com',
                    'autocomplete' => 'email'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter first name',
                    'autocomplete' => 'given-name'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Enter last name',
                    'autocomplete' => 'family-name'
                ]
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'help' => 'Include country code (e.g., +237 123 456 789)',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => '+237 123 456 789',
                    'autocomplete' => 'tel'
                ]
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'help' => 'Upload a profile picture (JPEG, PNG, WEBP, GIF - Max 2MB)',
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
                    'class' => 'form-control form-control-lg',
                    'accept' => 'image/*',
                    'onchange' => 'previewAvatar(this)'
                ]
            ])
            ->add('userRoles', EntityType::class, [
                'class' => Role::class,
                'choice_label' => function(Role $role) {
                    return $role->getDisplayName() . ' (' . $role->getName() . ')';
                },
                'choice_attr' => function(Role $role) {
                    return [
                        'data-description' => $role->getDescription() ?: 'No description available',
                        'data-system' => $role->isSystem() ? 'true' : 'false'
                    ];
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'User Roles',
                'help' => 'Select one or more roles for this user. System roles are protected.',
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('r')
                        ->where('r.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('r.displayOrder', 'ASC')
                        ->addOrderBy('r.name', 'ASC');
                },
                'attr' => [
                    'class' => 'roles-selection-grid'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active User',
                'required' => false,
                'data' => true,
                'help' => 'Inactive users cannot login to the system',
                'attr' => [
                    'class' => 'form-check-input form-check-input-lg'
                ]
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => 'Email Verified',
                'required' => false,
                'help' => 'Verified users have confirmed their email address',
                'attr' => [
                    'class' => 'form-check-input form-check-input-lg'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'data' => 0,
                'help' => 'Lower numbers appear first in lists (0 = highest priority)',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'min' => 0,
                    'max' => 9999
                ]
            ]);

        // Password field configuration based on context
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
                        'minMessage' => 'Password must be at least {{ limit }} characters long',
                        'max' => 4096
                    ]),
                ],
                'first_options' => [
                    'label' => 'Password',
                    'help' => 'Minimum 6 characters, include letters and numbers for security',
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Enter secure password'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'help' => 'Re-enter the same password for confirmation',
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Confirm password'
                    ]
                ],
                'invalid_message' => 'Password confirmation does not match.',
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
                        'minMessage' => 'Password must be at least {{ limit }} characters long',
                        'max' => 4096,
                    ]),
                ],
                'first_options' => [
                    'label' => 'New Password',
                    'help' => 'Leave blank to keep current password. Minimum 6 characters.',
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Enter new password (optional)'
                    ]
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'help' => 'Re-enter the new password for confirmation',
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Confirm new password'
                    ]
                ],
                'invalid_message' => 'Password confirmation does not match.',
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