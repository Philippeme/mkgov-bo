<?php

namespace App\Controller\Admin;

use App\Entity\Person;
use App\Form\PersonType;
use App\Repository\PersonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/person')]
class PersonController extends AbstractController
{
    #[Route('/', name: 'admin_person_index', methods: ['GET'])]
    public function index(personRepository $personRepository): Response
    {
        // Optimisation : récupération avec une limite pour éviter les problèmes de mémoire
        $persons = $personRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC'], 100);
        
        return $this->render('admin/person/index.html.twig', [
            'persons' => $persons,
        ]);
    }

    #[Route('/new', name: 'admin_person_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $person = new person();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(personType::class, $person, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                // Gestion de l'upload d'image avec optimisation mémoire
                $photoFile = $form->get('photoFile')->getData();
                if ($photoFile) {
                    $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('photoFile');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $photoFile->move($uploadsDirectory, $newFilename);
                        $person->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_person_new');
                    }
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($person);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Le projet a été créé avec succès.');
                return $this->redirectToRoute('admin_person_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_person_new');
            }
        }

        return $this->render('admin/person/new.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_person_show', methods: ['GET'])]
    public function show(person $person): Response
    {
        return $this->render('admin/person/show.html.twig', [
            'person' => $person,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_person_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, person $person, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(personType::class, $person, [
            'validation_groups' => ['Default'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {

                // Gestion de l'upload d'image avec optimisation mémoire
                $photoFile = $form->get('photoFile')->getData();  
                         

                if ($photoFile) {
                    // Supprimer l'ancienne image si elle existe
                    if ($person->getImage()) {
                        $oldImagePath = $this->getParameter('projects_directory').'/'.$person->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$photoFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('projects_directory');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $photoFile->move($uploadsDirectory, $newFilename);
                        $person->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Erreur lors du téléchargement de l\'image : ' . $e->getMessage());
                        return $this->redirectToRoute('admin_person_edit', ['id' => $person->getId()]);
                    }
                }
                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Family has been modified successfully.');
                return $this->redirectToRoute('admin_person_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du projet : ' . $e->getMessage());
                return $this->redirectToRoute('admin_person_edit', ['id' => $person->getId()]);
            }
        }

        return $this->render('admin/person/edit.html.twig', [
            'person' => $person,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_person_delete', methods: ['POST'])]
    public function delete(Request $request, person $person, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$person->getId(), $request->request->get('_token'))) {
            try {

                // Supprimer l'image si elle existe
                if ($person->getImage()) {
                    $imagePath = $this->getParameter('procedures_directory').'/'.$person->getImage();
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }

                $entityManager->remove($person);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'Person has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la suppression du projet : ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_person_index', [], Response::HTTP_SEE_OTHER);
    }
}