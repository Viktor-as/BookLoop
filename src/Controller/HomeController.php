<?php

namespace App\Controller;

use App\Repository\CategoriesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(private readonly CategoriesRepository $categoriesRepository) {}

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        $categories = $this->categoriesRepository->findBy([], ['name' => 'ASC']);

        return $this->render('home/index.html.twig', [
            'categories' => $categories,
        ]);
    }
}
