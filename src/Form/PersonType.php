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
            // Basic Information
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
            ->add('gender', ChoiceType::class, [
                'label' => 'Gender',
                'choices' => [
                    'Male' => 'M',
                    'Female' => 'F'
                ],
                'attr' => ['class' => 'form-select'],
                'placeholder' => 'Select gender'
            ])
            ->add('dateOfBirth', DateType::class, [
                'label' => 'Date of Birth',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control']
            ])
            ->add('placeOfBirth', TextType::class, [
                'label' => 'Place of Birth',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter place of birth']
            ])
            
            // Contact Information
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter email address']
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Phone Number',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter phone number']
            ])
            
            // Identity Information
            ->add('nationalId', TextType::class, [
                'label' => 'National ID Number',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter national ID number']
            ])
            ->add('nationality', TextType::class, [
                'label' => 'Nationality',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter nationality'],
                'data' => $options['data']->getNationality() ?: 'Cameroonian'
            ])
            
            // Address Information
            ->add('address', TextareaType::class, [
                'label' => 'Address',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Enter complete address']
            ])
            ->add('city', TextType::class, [
                'label' => 'City',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter city']
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
            ->add('postalCode', TextType::class, [
                'label' => 'Postal Code',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter postal code']
            ])
            
            // Professional Information
            ->add('profession', TextType::class, [
                'label' => 'Profession',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter profession']
            ])
            ->add('employer', TextType::class, [
                'label' => 'Employer',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter employer name']
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
            
            // Photo Management
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
                'attr' => ['class' => 'form-control', 'accept' => 'image/*', 'onchange' => 'previewPhoto(this)']
            ])
            ->add('removePhoto', CheckboxType::class, [
                'label' => 'Remove current photo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            
            // Emergency Contact Fields (mapped as individual fields, combined in controller)
            ->add('emergencyContactName', TextType::class, [
                'label' => 'Emergency Contact Name',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter emergency contact name'],
                'data' => $options['data']->getEmergencyContact()['name'] ?? null
            ])
            ->add('emergencyContactEmail', EmailType::class, [
                'label' => 'Emergency Contact Email',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter emergency contact email'],
                'data' => $options['data']->getEmergencyContact()['email'] ?? null
            ])
            ->add('emergencyContactPhone', TelType::class, [
                'label' => 'Emergency Contact Phone',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter emergency contact phone'],
                'data' => $options['data']->getEmergencyContact()['phone'] ?? null
            ])
            ->add('emergencyContactRelation', ChoiceType::class, [
                'label' => 'Emergency Contact Relation',
                'mapped' => false,
                'required' => false,
                'choices' => [
                    'Spouse' => 'spouse',
                    'Parent' => 'parent',
                    'Child' => 'child',
                    'Sibling' => 'sibling',
                    'Colleague' => 'colleague',
                    'Friend' => 'friend',
                    'Other' => 'other'
                ],
                'placeholder' => 'Select relationship',
                'attr' => ['class' => 'form-select'],
                'data' => $options['data']->getEmergencyContact()['relation'] ?? null
            ])
            
            // Administrative Fields
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'data' => $options['data']->getDisplayOrder() ?: 0
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Person::class,
        ]);
    }
}