<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // CORRECTION: Redirection systématique vers l'administration si déjà connecté
        if ($this->getUser() && $this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_project_index');
        }

        // Récupérer l'erreur de connexion si elle existe
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Dernier nom d'utilisateur saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): Response
    {
        // CORRECTION: Cette méthode sera interceptée par le système de sécurité de Symfony
        // La redirection se fait automatiquement vers app_login selon la configuration security.yaml
        // En cas d'accès direct à cette route, redirection manuelle vers la page de connexion
        return $this->redirectToRoute('app_login');
    }
    
    /**
     * Route d'accès direct à l'administration - Protection renforcée
     * Redirige systématiquement vers la page de connexion si non authentifié
     */
    #[Route(path: '/admin', name: 'admin_dashboard')]
    public function adminDashboard(): Response
    {
        // Vérification de l'authentification et redirection vers la gestion des projets
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_login');
        }
        
        // Redirection vers la page principale d'administration
        return $this->redirectToRoute('admin_project_index');
    }
}