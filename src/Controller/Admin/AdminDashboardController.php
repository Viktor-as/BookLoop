<?php

namespace App\Controller\Admin;

use App\Entity\Authors;
use App\Entity\Books;
use App\Entity\Categories;
use App\Entity\Settings;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $counts = [
            'authors'    => (int) $em->getRepository(Authors::class)->count([]),
            'categories' => (int) $em->getRepository(Categories::class)->count([]),
            'books'      => (int) $em->getRepository(Books::class)->count([]),
            'settings'   => (int) $em->getRepository(Settings::class)->count([]),
            'users'      => (int) $em->getRepository(Users::class)->count([]),
        ];

        return $this->render('admin/dashboard.html.twig', ['counts' => $counts]);
    }
}
