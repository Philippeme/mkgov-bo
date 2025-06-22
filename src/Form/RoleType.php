<?php

namespace App\Form;

use App\Entity\Permission;
use App\Entity\Role;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Role Name',
                'help' => 'Must start with ROLE_ and contain only uppercase letters and underscores (e.g., ROLE_MANAGER)',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'e.g., ROLE_MANAGER',
                    'pattern' => '^ROLE_[A-Z_]+$',
                    'style' => 'font-family: monospace;',
                    'oninput' => 'this.value = this.value.toUpperCase()'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Role name is required']),
                    new Length(['min' => 5, 'max' => 100]),
                    new Regex([
                        'pattern' => '/^ROLE_[A-Z_]+$/',
                        'message' => 'Role name must start with ROLE_ and contain only uppercase letters and underscores'
                    ])
                ]
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Display Name',
                'help' => 'Human-readable name for the role (shown in user interfaces)',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'e.g., Manager',
                    'maxlength' => 255
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Display name is required']),
                    new Length(['min' => 2, 'max' => 255])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => 'Brief description of the role and its intended use',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Describe the role\'s purpose, responsibilities, and scope of access...',
                    'maxlength' => 1000,
                    'onkeyup' => 'updateCharCount(this, 1000)'
                ]
            ])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'choice_label' => function(Permission $permission) {
                    return $permission->getName();
                },
                'choice_attr' => function(Permission $permission) {
                    return [
                        'data-description' => $permission->getDescription() ?: 'No description available',
                        'data-order' => $permission->getDisplayOrder()
                    ];
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'label' => 'Permissions',
                'help' => 'Select the permissions this role should have. Permissions define what actions users with this role can perform.',
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->orderBy('p.displayOrder', 'ASC')
                        ->addOrderBy('p.name', 'ASC');
                },
                'attr' => [
                    'class' => 'permissions-grid'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active Role',
                'required' => false,
                'data' => true,
                'help' => 'Only active roles can be assigned to users',
                'attr' => [
                    'class' => 'form-check-input form-check-input-lg'
                ]
            ])
            ->add('isSystem', CheckboxType::class, [
                'label' => 'System Role',
                'required' => false,
                'help' => 'System roles are protected from deletion and modification by regular users',
                'attr' => [
                    'class' => 'form-check-input form-check-input-lg',
                    'onchange' => 'toggleSystemRoleWarning(this)'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'data' => 0,
                'help' => 'Order in which roles appear in lists (lower numbers first, 0 = highest priority)',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'min' => 0,
                    'max' => 9999,
                    'placeholder' => '0'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
        ]);
    }
}