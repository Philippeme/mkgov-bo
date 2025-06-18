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

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Role Name',
                'help' => 'Must start with ROLE_ and contain only uppercase letters and underscores (e.g., ROLE_MANAGER)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., ROLE_MANAGER',
                    'pattern' => '^ROLE_[A-Z_]+$'
                ]
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Display Name',
                'help' => 'Human-readable name for the role',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., Manager'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'help' => 'Brief description of the role and its permissions',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Describe what this role can do...'
                ]
            ])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'choice_label' => function(Permission $permission) {
                    return $permission->getName() . ' - ' . ($permission->getDescription() ?: 'No description');
                },
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->orderBy('p.displayOrder', 'ASC')
                        ->addOrderBy('p.name', 'ASC');
                },
                'attr' => [
                    'class' => 'permissions-checkboxes'
                ],
                'help' => 'Select the permissions this role should have'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active Role',
                'required' => false,
                'help' => 'Inactive roles cannot be assigned to users',
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('isSystem', CheckboxType::class, [
                'label' => 'System Role',
                'required' => false,
                'help' => 'System roles are protected and cannot be deleted',
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'help' => 'Order in which roles appear in lists (lower numbers first)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
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