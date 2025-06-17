<?php

namespace App\Form;

use App\Entity\Role;
use App\Entity\Permission;
use App\Repository\PermissionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Role Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., ROLE_ADMIN, ROLE_USER, ROLE_MANAGER'
                ],
                'help' => 'Role name must start with ROLE_ and contain only uppercase letters and underscores'
            ])
            ->add('label', TextType::class, [
                'label' => 'Display Label',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., Administrator, User, Manager'
                ],
                'help' => 'Human-readable label for this role'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Describe what this role is for and what permissions it should have'
                ],
                'help' => 'Detailed description of this role'
            ])
            ->add('badgeColor', ChoiceType::class, [
                'label' => 'Badge Color',
                'choices' => [
                    'Primary (Blue)' => 'primary',
                    'Secondary (Gray)' => 'secondary',
                    'Success (Green)' => 'success',
                    'Danger (Red)' => 'danger',
                    'Warning (Yellow)' => 'warning',
                    'Info (Cyan)' => 'info',
                    'Light (Light Gray)' => 'light',
                    'Dark (Dark Gray)' => 'dark',
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Color for displaying this role in badges and lists'
            ])
            ->add('permissions', EntityType::class, [
                'class' => Permission::class,
                'query_builder' => function (PermissionRepository $repository) {
                    return $repository->createQueryBuilder('p')
                        ->andWhere('p.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('p.category', 'ASC')
                        ->addOrderBy('p.displayOrder', 'ASC');
                },
                'choice_label' => function (Permission $permission) {
                    return $permission->getLabel() . ' (' . $permission->getName() . ')';
                },
                'group_by' => function (Permission $permission) {
                    return $permission->getCategory();
                },
                'multiple' => true,
                'expanded' => true,
                'label' => 'Permissions',
                'required' => false,
                'attr' => [
                    'class' => 'permissions-checkboxes'
                ],
                'help' => 'Select permissions for this role. Permissions are grouped by category.'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Inactive roles cannot be assigned to users'
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'help' => 'Order for sorting roles (lower numbers appear first)'
            ]);

        // Add system role checkbox only for editing existing roles
        if ($isEdit) {
            $builder->add('isSystem', CheckboxType::class, [
                'label' => 'System Role',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'System roles cannot be deleted and have restricted modification'
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
            'is_edit' => false,
        ]);
    }
}