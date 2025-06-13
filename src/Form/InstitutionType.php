<?php

namespace App\Form;

use App\Entity\Institution;
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

class InstitutionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('iname', TextType::class, [
                'label' => 'Institution name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('hq', TextareaType::class, [
                'label' => 'Heardquarter',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('department', TextareaType::class, [
                'label' => 'Department',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('phonenumber', TextareaType::class, [
                'label' => 'Phone number',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('email', TextareaType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control', 'rows' => 6]
            ])
            ->add('contactperson', TextareaType::class, [
                'label' => 'Contact person',
                'attr' => ['class' => 'form-control', 'rows' => 6]
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
            'data_class' => Institution::class,
        ]);
    }
}