<?php

namespace App\Controller\Admin;

use App\Entity\Person;
use App\Form\PersonType;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/person')]
class PersonController extends AbstractController
{
    #[Route('/', name: 'admin_person_index', methods: ['GET'])]
    public function index(PersonRepository $personRepository, Request $request): Response
    {
        $search = $request->query->get('search', '');
        $region = $request->query->get('region', '');
        $gender = $request->query->get('gender', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        $filters = [
            'search' => $search,
            'region' => $region,
            'gender' => $gender
        ];

        $persons = $personRepository->findWithFilters($filters, $page, $limit);
        
        // Get unique regions for filter
        $regions = [
            'Adamawa', 'Centre', 'East', 'Far North', 'Littoral',
            'North', 'Northwest', 'South', 'Southwest', 'West'
        ];
        
        return $this->render('admin/person/index.html.twig', [
            'persons' => $persons,
            'filters' => $filters,
            'regions' => $regions,
            'currentPage' => $page,
        ]);
    }

    #[Route('/new', name: 'admin_person_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $person = new Person();
        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle photo upload
                $photoFile = $form->get('photoFile')->getData();
                if ($photoFile) {
                    $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                    $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/persons';
                    if (!is_dir($uploadsDirectory)) {
                        mkdir($uploadsDirectory, 0755, true);
                    }
                    
                    $photoFile->move($uploadsDirectory, $newFilename);
                    $person->setImage($newFilename);
                }

                // Handle emergency contact
                $emergencyContactData = [];
                $emergencyName = $form->get('emergencyContactName')->getData();
                $emergencyEmail = $form->get('emergencyContactEmail')->getData();
                $emergencyPhone = $form->get('emergencyContactPhone')->getData();
                $emergencyRelation = $form->get('emergencyContactRelation')->getData();

                if ($emergencyName || $emergencyEmail || $emergencyPhone) {
                    $emergencyContactData = [
                        'name' => $emergencyName,
                        'email' => $emergencyEmail,
                        'phone' => $emergencyPhone,
                        'relation' => $emergencyRelation
                    ];
                    $person->setEmergencyContact($emergencyContactData);
                }

                $entityManager->persist($person);
                $entityManager->flush();

                $this->addFlash('success', 'Person has been created successfully.');
                return $this->redirectToRoute('admin_person_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating person: ' . $e->getMessage());
            }
        }

        return $this->render('admin/person/new.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_person_show', methods: ['GET'])]
    public function show(Person $person): Response
    {
        // Check if person is deleted
        if ($person->isDeleted()) {
            throw $this->createNotFoundException('Person not found.');
        }

        return $this->render('admin/person/show.html.twig', [
            'person' => $person,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_person_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Person $person, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Check if person is deleted
        if ($person->isDeleted()) {
            throw $this->createNotFoundException('Person not found.');
        }

        $form = $this->createForm(PersonType::class, $person);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle photo removal
                $removePhoto = $form->get('removePhoto')->getData();
                if ($removePhoto) {
                    if ($person->getImage()) {
                        $oldPhotoPath = $this->getParameter('kernel.project_dir').'/public/uploads/persons/'.$person->getImage();
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    $person->setImage(null);
                } else {
                    // Handle photo upload
                    $photoFile = $form->get('photoFile')->getData();
                    if ($photoFile) {
                        // Delete old photo if exists
                        if ($person->getImage()) {
                            $oldPhotoPath = $this->getParameter('kernel.project_dir').'/public/uploads/persons/'.$person->getImage();
                            if (file_exists($oldPhotoPath)) {
                                unlink($oldPhotoPath);
                            }
                        }

                        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                        $uploadsDirectory = $this->getParameter('kernel.project_dir').'/public/uploads/persons';
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $photoFile->move($uploadsDirectory, $newFilename);
                        $person->setImage($newFilename);
                    }
                }

                // Handle emergency contact
                $emergencyContactData = [];
                $emergencyName = $form->get('emergencyContactName')->getData();
                $emergencyEmail = $form->get('emergencyContactEmail')->getData();
                $emergencyPhone = $form->get('emergencyContactPhone')->getData();
                $emergencyRelation = $form->get('emergencyContactRelation')->getData();

                if ($emergencyName || $emergencyEmail || $emergencyPhone) {
                    $emergencyContactData = [
                        'name' => $emergencyName,
                        'email' => $emergencyEmail,
                        'phone' => $emergencyPhone,
                        'relation' => $emergencyRelation
                    ];
                    $person->setEmergencyContact($emergencyContactData);
                } else {
                    $person->setEmergencyContact(null);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Person has been updated successfully.');
                return $this->redirectToRoute('admin_person_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating person: ' . $e->getMessage());
            }
        }

        return $this->render('admin/person/edit.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_person_delete', methods: ['POST'])]
    public function delete(Request $request, Person $person, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$person->getId(), $request->request->get('_token'))) {
            try {
                // Soft delete - set isDeleted to true instead of removing from database
                $person->setIsDeleted(true);
                $entityManager->flush();

                $this->addFlash('success', 'Person has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting person: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_person_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/verify', name: 'admin_person_verify', methods: ['POST'])]
    public function verify(Person $person, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            if ($person->getVerifiedAt()) {
                $person->setVerifiedAt(null);
                $message = 'Person verification removed successfully.';
            } else {
                $person->setVerifiedAt(new \DateTime());
                $message = 'Person verified successfully.';
            }
            
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'verified' => $person->getVerifiedAt() !== null,
                'verifiedAt' => $person->getVerifiedAt()?->format('Y-m-d H:i:s'),
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating verification status: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/restore', name: 'admin_person_restore', methods: ['POST'])]
    public function restore(Person $person, EntityManagerInterface $entityManager): JsonResponse
    {
        try {
            $person->setIsDeleted(false);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Person restored successfully.'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error restoring person: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/search', name: 'admin_person_search', methods: ['GET'])]
    public function search(Request $request, PersonRepository $personRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $persons = $personRepository->searchByNameOrId($query, 10);
        
        $results = [];
        foreach ($persons as $person) {
            $results[] = [
                'id' => $person->getId(),
                'text' => sprintf('%s (%s)', $person->getFullName(), $person->getNationalId()),
                'email' => $person->getEmail(),
                'phone' => $person->getPhoneNumber()
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/export', name: 'admin_person_export', methods: ['GET'])]
    public function export(PersonRepository $personRepository): Response
    {
        $persons = $personRepository->findBy(['isDeleted' => false], ['lastName' => 'ASC']);
        
        $csvData = [];
        $csvData[] = [
            'ID', 'First Name', 'Last Name', 'Middle Name', 'Email', 'Phone', 
            'Gender', 'Date of Birth', 'National ID', 'Region', 'City', 
            'Profession', 'Marital Status', 'Created At'
        ];

        foreach ($persons as $person) {
            $csvData[] = [
                $person->getId(),
                $person->getFirstName(),
                $person->getLastName(),
                $person->getMiddleName(),
                $person->getEmail(),
                $person->getPhoneNumber(),
                $person->getGenderText(),
                $person->getDateOfBirth()?->format('Y-m-d'),
                $person->getNationalId(),
                $person->getRegion(),
                $person->getCity(),
                $person->getProfession(),
                $person->getMaritalStatus(),
                $person->getCreatedAt()?->format('Y-m-d H:i:s')
            ];
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="persons_export_'.date('Y-m-d').'.csv"');

        $output = fopen('php://output', 'w');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        fclose($output);

        return $response;
    }
}