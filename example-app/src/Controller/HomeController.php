<?php

declare(strict_types=1);

namespace App\Controller;

use Pasaia\SsoClientBundle\Security\OidcUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    #[IsGranted('ROLE_USER')]
    public function index(#[CurrentUser] OidcUser $user): Response
    {
        return $this->render('home/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/public', name: 'public_page')]
    public function publicPage(): Response
    {
        return $this->render('home/public.html.twig');
    }
}
