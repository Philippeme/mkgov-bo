<?php

namespace App\Form;

use App\Entity\Procedure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProcedureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pname', TextType::class, [
                'label' => 'Procedure name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('family', TextType::class, [
                'label' => 'Family',
                'attr' => ['class' => 'form-control']
            ])
            ->add('shortdesc', TextareaType::class, [
                'label' => 'Short description',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('longdesc', TextareaType::class, [
                'label' => 'Long description',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('processtime', IntegerType::class, [
                'label' => 'Processing time',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('servicecost', MoneyType::class, [
                'label' => 'Service cost',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Procedure image',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please download a image agree (JPEG, PNG, WEBP)',
                    ])
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Publish',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Procedure::class,
        ]);
    }
}