<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/user')]
class UserController extends AbstractController
{
    #[Route('/', name: 'admin_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        // Récupération des paramètres de filtrage depuis la requête
        $filters = [
            'search' => $request->query->get('search'),
            'role' => $request->query->get('role'),
            'active' => $request->query->get('active') !== null ? (bool) $request->query->get('active') : null,
            'verified' => $request->query->get('verified') !== null ? (bool) $request->query->get('verified') : null,
        ];

        // Suppression des filtres vides pour optimiser les requêtes
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        // Optimisation : récupération avec limite pour éviter les problèmes de mémoire
        $users = $userRepository->findUsersWithFilters($filters);
        $roles = $userRepository->findUniqueRoles();
        $statistics = $userRepository->countUsersByStatus();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $filters,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger): Response
    {
        $user = new User();
        
        // Configuration optimisée du formulaire pour réduire l'utilisation mémoire
        $form = $this->createForm(UserType::class, $user, [
            'validation_groups' => ['Default', 'Registration'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Hashage du mot de passe
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Gestion de l'upload d'avatar avec optimisation mémoire
                $avatarFile = $form->get('avatarFile')->getData();
                if ($avatarFile) {
                    $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('avatars_directory');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $avatarFile->move($uploadsDirectory, $newFilename);
                        $user->setAvatar($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Error uploading avatar: ' . $e->getMessage());
                        return $this->redirectToRoute('admin_user_new');
                    }
                }

                // Sauvegarde optimisée avec gestion d'erreur
                $entityManager->persist($user);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'User has been created successfully.');
                return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating user: ' . $e->getMessage());
                return $this->redirectToRoute('admin_user_new');
            }
        }

        return $this->render('admin/user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, SluggerInterface $slugger): Response
    {
        // Configuration optimisée du formulaire
        $form = $this->createForm(UserType::class, $user, [
            'validation_groups' => ['Default'],
            'is_edit' => true,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Hashage du nouveau mot de passe si fourni
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Gestion de l'upload d'avatar avec optimisation mémoire
                $avatarFile = $form->get('avatarFile')->getData();
                if ($avatarFile) {
                    // Supprimer l'ancien avatar si il existe
                    if ($user->getAvatar()) {
                        $oldAvatarPath = $this->getParameter('avatars_directory').'/'.$user->getAvatar();
                        if (file_exists($oldAvatarPath)) {
                            unlink($oldAvatarPath);
                        }
                    }

                    $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                    try {
                        // Vérification de l'existence du répertoire
                        $uploadsDirectory = $this->getParameter('avatars_directory');
                        if (!is_dir($uploadsDirectory)) {
                            mkdir($uploadsDirectory, 0755, true);
                        }
                        
                        $avatarFile->move($uploadsDirectory, $newFilename);
                        $user->setAvatar($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Error uploading avatar: ' . $e->getMessage());
                        return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
                    }
                }

                // Sauvegarde optimisée
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'User has been updated successfully.');
                return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating user: ' . $e->getMessage());
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            try {
                // Supprimer l'avatar si il existe
                if ($user->getAvatar()) {
                    $avatarPath = $this->getParameter('avatars_directory').'/'.$user->getAvatar();
                    if (file_exists($avatarPath)) {
                        unlink($avatarPath);
                    }
                }

                $entityManager->remove($user);
                $entityManager->flush();
                
                // Libération de la mémoire
                $entityManager->clear();

                $this->addFlash('success', 'User has been deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting user: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('toggle_status'.$user->getId(), $request->request->get('_token'))) {
            try {
                $user->setIsActive(!$user->isActive());
                $entityManager->flush();

                $status = $user->isActive() ? 'activated' : 'deactivated';
                $this->addFlash('success', "User has been {$status} successfully.");
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating user status: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/verify', name: 'admin_user_verify', methods: ['POST'])]
    public function verify(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('verify'.$user->getId(), $request->request->get('_token'))) {
            try {
                $user->setIsVerified(!$user->isVerified());
                $entityManager->flush();

                $status = $user->isVerified() ? 'verified' : 'unverified';
                $this->addFlash('success', "User has been marked as {$status} successfully.");
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating user verification status: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/bulk-action', name: 'admin_user_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $action = $request->request->get('action');
        $userIds = $request->request->all('users');

        if (!$action || empty($userIds)) {
            $this->addFlash('error', 'Please select users and an action.');
            return $this->redirectToRoute('admin_user_index');
        }

        try {
            $users = $userRepository->findBy(['id' => $userIds]);
            $count = 0;

            foreach ($users as $user) {
                switch ($action) {
                    case 'activate':
                        $user->setIsActive(true);
                        $count++;
                        break;
                    case 'deactivate':
                        $user->setIsActive(false);
                        $count++;
                        break;
                    case 'verify':
                        $user->setIsVerified(true);
                        $count++;
                        break;
                    case 'unverify':
                        $user->setIsVerified(false);
                        $count++;
                        break;
                    case 'delete':
                        // Supprimer l'avatar si il existe
                        if ($user->getAvatar()) {
                            $avatarPath = $this->getParameter('avatars_directory').'/'.$user->getAvatar();
                            if (file_exists($avatarPath)) {
                                unlink($avatarPath);
                            }
                        }
                        $entityManager->remove($user);
                        $count++;
                        break;
                }
            }

            $entityManager->flush();
            $entityManager->clear();

            $this->addFlash('success', "Bulk action '{$action}' applied to {$count} users successfully.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error performing bulk action: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }
}