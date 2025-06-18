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
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);
        
        $filters = [
            'search' => $request->query->get('search'),
            'role' => $request->query->get('role'),
            'verified' => $request->query->get('verified'),
        ];

        $users = $userRepository->findActiveUsersWithFilters($filters, $page, $limit);
        $statistics = $userRepository->getUserStatistics();
        
        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'statistics' => $statistics,
            'filters' => $filters,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $user = new User();
        
        $form = $this->createForm(UserType::class, $user, [
            'validation_groups' => ['Default', 'Registration'],
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Hash password
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Handle avatar upload
                $avatarFile = $form->get('avatarFile')->getData();
                if ($avatarFile) {
                    $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                    try {
                        $uploadsDirectory = $this->getParameter('users_directory');
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

                $entityManager->persist($user);
                $entityManager->flush();
                
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
    public function edit(
        Request $request, 
        User $user, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'validation_groups' => ['Default'],
            'is_edit' => true,
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Hash password if provided
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }

                // Handle avatar upload
                $avatarFile = $form->get('avatarFile')->getData();
                if ($avatarFile) {
                    // Remove old avatar if exists
                    if ($user->getAvatar()) {
                        $oldAvatarPath = $this->getParameter('users_directory').'/'.$user->getAvatar();
                        if (file_exists($oldAvatarPath)) {
                            unlink($oldAvatarPath);
                        }
                    }

                    $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                    try {
                        $uploadsDirectory = $this->getParameter('users_directory');
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

                $entityManager->flush();
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
                // Remove avatar if exists
                if ($user->getAvatar()) {
                    $avatarPath = $this->getParameter('users_directory').'/'.$user->getAvatar();
                    if (file_exists($avatarPath)) {
                        unlink($avatarPath);
                    }
                }

                $entityManager->remove($user);
                $entityManager->flush();
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
        if ($this->isCsrfTokenValid('toggle'.$user->getId(), $request->request->get('_token'))) {
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
                $user->setIsVerified(true);
                $entityManager->flush();

                $this->addFlash('success', 'User has been verified successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error verifying user: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_user_index', [], Response::HTTP_SEE_OTHER);
    }
}