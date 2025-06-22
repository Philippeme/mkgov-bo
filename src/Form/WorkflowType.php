<?php

namespace App\Form;

use App\Entity\Procedure;
use App\Entity\Workflow;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkflowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Step Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter step name (e.g., Document Submission, Payment, etc.)'
                ]
            ])
            ->add('shortDescription', TextareaType::class, [
                'label' => 'Short Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Describe what happens in this step'
                ]
            ])
            ->add('procedure', EntityType::class, [
                'class' => Procedure::class,
                'choice_label' => 'pname',
                'label' => 'Procedure',
                'placeholder' => 'Select a procedure',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->where('p.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('p.pname', 'ASC');
                }
            ])
            ->add('inputs', ChoiceType::class, [
                'label' => 'Required Inputs',
                'choices' => Workflow::getAvailableInputs(),
                'multiple' => true,
                'expanded' => false,
                'attr' => [
                    'class' => 'form-select',
                    'multiple' => true,
                    'size' => 8
                ],
                'help' => 'Select all documents/inputs required for this step (hold Ctrl/Cmd to select multiple)'
            ])
            ->add('output', ChoiceType::class, [
                'label' => 'Step Output',
                'choices' => Workflow::getAvailableOutputs(),
                'placeholder' => 'Select the output of this step',
                'attr' => [
                    'class' => 'form-select'
                ],
                'help' => 'What is produced/achieved when this step is completed?'
            ])
            ->add('stepOrder', IntegerType::class, [
                'label' => 'Step Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Order in which this step occurs'
                ],
                'help' => 'The sequential order of this step in the procedure (1, 2, 3, etc.)'
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'data' => $options['data']->getDisplayOrder() ?: 0,
                'help' => 'Order for displaying this step (for sorting purposes)'
            ])
            ->add('isRequired', CheckboxType::class, [
                'label' => 'Required Step',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'data' => $options['data']->isRequired() !== false,
                'help' => 'Is this step mandatory for completing the procedure?'
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'data' => $options['data']->isActive() !== false,
                'help' => 'Is this step currently active and available?'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Workflow::class,
        ]);
    }
}