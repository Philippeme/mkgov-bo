<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/project')]
class ProjectController extends AbstractController
{
    #[Route('/', name: 'admin_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->findBy([], ['displayOrder' => 'ASC', 'createdAt' => 'DESC']);
        
        return $this->render('admin/project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/new', name: 'admin_project_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('admin/project/new.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}