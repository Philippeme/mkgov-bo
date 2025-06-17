<?php

namespace App\Form;

use App\Entity\Permission;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Permission Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., create_user, read_project, manage_system'
                ],
                'help' => 'Permission name must contain only lowercase letters and underscores'
            ])
            ->add('label', TextType::class, [
                'label' => 'Display Label',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., Create User, View Projects, Manage System'
                ],
                'help' => 'Human-readable label for this permission'
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Describe what this permission allows users to do'
                ],
                'help' => 'Detailed description of what this permission grants'
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Category',
                'choices' => [
                    'User Management' => 'user_management',
                    'Role Management' => 'role_management',
                    'Permission Management' => 'permission_management',
                    'Project Management' => 'project_management',
                    'Procedure Management' => 'procedure_management',
                    'Institution Management' => 'institution_management',
                    'Family Management' => 'family_management',
                    'Document Management' => 'document_management',
                    'Person Management' => 'person_management',
                    'System Administration' => 'system_administration',
                    'Content Management' => 'content_management',
                    'Security' => 'security',
                    'Reporting' => 'reporting',
                    'Settings' => 'settings',
                    'API Access' => 'api_access',
                    'Other' => 'other',
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Group permissions by category for better organization'
            ])
            ->add('action', ChoiceType::class, [
                'label' => 'Action Type',
                'required' => false,
                'choices' => [
                    'Create' => 'create',
                    'Read/View' => 'read',
                    'Update/Edit' => 'update',
                    'Delete' => 'delete',
                    'Manage (Full Access)' => 'manage',
                    'Execute/Run' => 'execute',
                    'View Only' => 'view',
                    'Admin Access' => 'admin',
                ],
                'placeholder' => 'Select an action type',
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'Type of action this permission allows'
            ])
            ->add('resource', TextType::class, [
                'label' => 'Resource',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., user, project, system, report'
                ],
                'help' => 'The resource this permission applies to (optional)'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Inactive permissions cannot be assigned to roles'
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'help' => 'Order for sorting permissions within a category (lower numbers appear first)'
            ]);

        // Add system permission checkbox only for editing existing permissions
        if ($isEdit) {
            $builder->add('isSystem', CheckboxType::class, [
                'label' => 'System Permission',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'System permissions cannot be deleted and have restricted modification'
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Permission::class,
            'is_edit' => false,
        ]);
    }
}