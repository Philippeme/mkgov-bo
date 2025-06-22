<?php

namespace App\Form;

use App\Entity\Family;
use App\Entity\Procedure;
use App\Entity\PublicEntity;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProcedureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pname', TextType::class, [
                'label' => 'Procedure Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter procedure name'
                ]
            ])
            ->add('family', EntityType::class, [
                'class' => Family::class,
                'choice_label' => 'fname',
                'label' => 'Service Family',
                'placeholder' => 'Select a service family',
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('f')
                        ->where('f.isActive = :active')
                        ->setParameter('active', true)
                        ->orderBy('f.displayOrder', 'ASC')
                        ->addOrderBy('f.fname', 'ASC');
                }
            ])
            ->add('providingAdministration', EntityType::class, [
                'class' => PublicEntity::class,
                'choice_label' => 'institutionName',
                'label' => 'Providing Administration',
                'placeholder' => 'Select providing administration (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'form-select'
                ],
                'query_builder' => function ($repository) {
                    return $repository->createQueryBuilder('pe')
                        ->leftJoin('pe.department', 'd')
                        ->addSelect('d')
                        ->where('pe.isActive = :active')
                        ->andWhere('pe.status = :status')
                        ->setParameter('active', true)
                        ->setParameter('status', 'active')
                        ->orderBy('pe.displayOrder', 'ASC')
                        ->addOrderBy('pe.institutionName', 'ASC');
                }
            ])
            ->add('shortdesc', TextareaType::class, [
                'label' => 'Short Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Enter short description'
                ]
            ])
            ->add('longdesc', TextareaType::class, [
                'label' => 'Long Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 6,
                    'placeholder' => 'Enter detailed description'
                ]
            ])
            ->add('processtime', TextType::class, [
                'label' => 'Process Time',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'e.g., 5-7 working days'
                ]
            ])
            ->add('servicecost', MoneyType::class, [
                'label' => 'Service Cost',
                'currency' => 'XAF',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00'
                ]
            ])
            ->add('imageBootstrap', ChoiceType::class, [
                'label' => 'Template Image',
                'mapped' => false,
                'required' => false,
                'choices' => $this->getBootstrapImages(),
                'attr' => [
                    'class' => 'form-select',
                    'onchange' => 'updateImagePreview(this.value)'
                ],
                'placeholder' => 'Select a template image'
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Custom Image File',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPEG, PNG, WebP)',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/*'
                ]
            ])
            ->add('removeImage', CheckboxType::class, [
                'label' => 'Remove current image',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('legalTextTemplate', ChoiceType::class, [
                'label' => 'Legal Document Template',
                'mapped' => false,
                'required' => false,
                'choices' => $this->getLegalTextTemplates(),
                'attr' => [
                    'class' => 'form-select',
                    'onchange' => 'updateLegalTextPreview(this.value)'
                ],
                'placeholder' => 'Select a legal document template'
            ])
            ->add('legalTextFile', FileType::class, [
                'label' => 'Custom Legal Text Document (PDF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '10M',
                        'mimeTypes' => [
                            'application/pdf'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid PDF file',
                    ])
                ],
                'attr' => [
                    'class' => 'form-control',
                    'accept' => '.pdf'
                ]
            ])
            ->add('removeLegalText', CheckboxType::class, [
                'label' => 'Remove current legal document',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('displayOrder', IntegerType::class, [
                'label' => 'Display Order',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0
                ],
                'data' => $options['data']->getDisplayOrder() ?: 0
            ])
            ->add('published', CheckboxType::class, [
                'label' => 'Published',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'data' => $options['data']->isPublished() !== false
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'data' => $options['data']->isActive() !== false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Procedure::class,
        ]);
    }

    private function getBootstrapImages(): array
    {
        return [
            'Passeport' => 'passport-template.jpg',
            'Carte d\'identité' => 'id-card-template.jpg',
            'Certificat de naissance' => 'birth-certificate-template.jpg',
            'Certificat de mariage' => 'marriage-certificate-template.jpg',
            'Certificat de décès' => 'death-certificate-template.jpg',
            'Permis de conduire' => 'driving-license-template.jpg',
            'Diplôme/Certification' => 'diploma-template.jpg',
            'Licence commerciale' => 'business-license-template.jpg',
            'Titre foncier' => 'land-title-template.jpg',
            'Visa' => 'visa-template.jpg',
            'Autorisation médicale' => 'medical-authorization-template.jpg',
            'Document général' => 'general-document-template.jpg'
        ];
    }

    private function getLegalTextTemplates(): array
    {
        return [
            'Cadre légal - Police & Justice' => 'legal-police-justice.pdf',
            'Cadre légal - État Civil' => 'legal-civil-status.pdf',
            'Cadre légal - Transport' => 'legal-transport.pdf',
            'Cadre légal - Éducation' => 'legal-education.pdf',
            'Cadre légal - Entreprises' => 'legal-business.pdf',
            'Cadre légal - Fonction Publique' => 'legal-public-service.pdf',
            'Cadre légal - Terre et Construction' => 'legal-land-construction.pdf',
            'Cadre légal - Services Consulaires' => 'legal-consular.pdf',
            'Cadre légal - Santé' => 'legal-health.pdf',
            'Cadre légal - Vie Citoyenne' => 'legal-civic-life.pdf'
        ];
    }
}