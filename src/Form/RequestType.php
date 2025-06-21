<?php

namespace App\Form;

use App\Entity\Person;
use App\Entity\Procedure;
use App\Entity\Request;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('person', EntityType::class, [
                'class' => Person::class,
                'choice_label' => function(Person $person) {
                    return sprintf('%s (%s)', $person->getFullName(), $person->getNationalId());
                },
                'label' => 'Citizen',
                'placeholder' => 'Select a citizen',
                'attr' => [
                    'class' => 'form-select',
                    'data-search' => 'true'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->where('p.isDeleted = :deleted')
                        ->setParameter('deleted', false)
                        ->orderBy('p.lastName', 'ASC')
                        ->addOrderBy('p.firstName', 'ASC');
                }
            ])
            ->add('procedure', EntityType::class, [
                'class' => Procedure::class,
                'choice_label' => 'pname',
                'label' => 'Procedure',
                'placeholder' => 'Select a procedure',
                'attr' => [
                    'class' => 'form-select',
                    'onchange' => 'updateCostAndTime(this.value)'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('p')
                        ->leftJoin('p.family', 'f')
                        ->addSelect('f')
                        ->where('p.isActive = :active')
                        ->andWhere('p.published = :published')
                        ->setParameter('active', true)
                        ->setParameter('published', true)
                        ->orderBy('f.fname', 'ASC')
                        ->addOrderBy('p.pname', 'ASC');
                }
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Processing' => 'processing',
                    'Completed' => 'completed',
                    'Rejected' => 'rejected',
                    'Cancelled' => 'cancelled'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priority',
                'choices' => [
                    'Low' => 'low',
                    'Normal' => 'normal',
                    'High' => 'high',
                    'Urgent' => 'urgent'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('totalCost', MoneyType::class, [
                'label' => 'Total Cost',
                'currency' => 'XAF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00',
                    'readonly' => true,
                    'id' => 'request_totalCost'
                ]
            ])
            ->add('paidAmount', MoneyType::class, [
                'label' => 'Paid Amount',
                'currency' => 'XAF',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00'
                ]
            ])
            ->add('paymentStatus', ChoiceType::class, [
                'label' => 'Payment Status',
                'choices' => [
                    'Pending' => 'pending',
                    'Partial' => 'partial',
                    'Completed' => 'completed',
                    'Refunded' => 'refunded'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('expectedCompletionAt', DateTimeType::class, [
                'label' => 'Expected Completion Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'request_expectedCompletionAt'
                ]
            ])
            ->add('completedAt', DateTimeType::class, [
                'label' => 'Completion Date',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('comments', TextareaType::class, [
                'label' => 'Citizen Comments',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Any additional comments or special requests from the citizen'
                ]
            ])
            ->add('adminNotes', TextareaType::class, [
                'label' => 'Administrative Notes',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Internal notes for administrative purposes'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'data' => $options['data']->getDisplayOrder() ?: 0
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Request::class,
        ]);
    }
}