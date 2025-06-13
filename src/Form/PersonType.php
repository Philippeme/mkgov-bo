<?php

namespace App\Form;

use App\Entity\Person;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class PersonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter first name']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter last name']
            ])
            ->add('middleName', TextType::class, [
                'label' => 'Middle Name',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter middle name (optional)']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter email address']
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter phone number']
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date of Birth',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('gender', ChoiceType::class, [
                'label' => 'Gender',
                'choices' => [
                    'Male' => 'M',
                    'Female' => 'F'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('nationalId', TextType::class, [
                'label' => 'National ID Number',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter national ID number']
            ])
            ->add('placeOfBirth', TextType::class, [
                'label' => 'Place of Birth',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter place of birth']
            ])
            ->add('address', TextareaType::class, [
                'label' => 'Address',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Enter complete address']
            ])
            ->add('region', ChoiceType::class, [
                'label' => 'Region',
                'required' => false,
                'choices' => [
                    'Adamawa' => 'Adamawa',
                    'Centre' => 'Centre',
                    'East' => 'East',
                    'Far North' => 'Far North',
                    'Littoral' => 'Littoral',
                    'North' => 'North',
                    'Northwest' => 'Northwest',
                    'South' => 'South',
                    'Southwest' => 'Southwest',
                    'West' => 'West'
                ],
                'placeholder' => 'Select a region',
                'attr' => ['class' => 'form-select']
            ])
            ->add('profession', TextType::class, [
                'label' => 'Profession',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter profession']
            ])
            ->add('maritalStatus', ChoiceType::class, [
                'label' => 'Marital Status',
                'required' => false,
                'choices' => [
                    'Single' => 'single',
                    'Married' => 'married',
                    'Divorced' => 'divorced',
                    'Widowed' => 'widowed'
                ],
                'placeholder' => 'Select marital status',
                'attr' => ['class' => 'form-select']
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, WEBP)'
                    ])
                ],
                'attr' => ['class' => 'form-control', 'accept' => 'image/*']
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Ordre d\'affichage',
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Person::class,
        ]);
    }
}