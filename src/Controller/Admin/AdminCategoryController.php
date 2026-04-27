<?php

namespace App\Controller\Admin;

use App\Entity\Categories;
use App\Form\Admin\CategoryFormType;
use App\Repository\BookCategoryRepository;
use App\Repository\CategoriesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
final class AdminCategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoriesRepository $categoriesRepository,
        private readonly BookCategoryRepository $bookCategoryRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_categories_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page    = max(1, $request->query->getInt('page', 1));
        $perPage = 10;

        $qb = $this->categoriesRepository->createQueryBuilder('c')
            ->orderBy('c.updatedAt', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator   = new Paginator($qb);
        $totalItems  = $paginator->count();
        $maxPage     = max(1, (int) ceil($totalItems / $perPage));
        if ($page > $maxPage) {
            return $this->redirectToRoute('admin_categories_index', ['page' => $maxPage]);
        }

        $categories = iterator_to_array($paginator);

        return $this->render('admin/categories/index.html.twig', [
            'categories' => $categories,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalItems' => $totalItems,
        ]);
    }

    #[Route('/new', name: 'admin_categories_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new Categories();
        $form     = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', 'Category created.');

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form'  => $form,
            'title' => 'New category',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_categories_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Categories $category): Response
    {
        $form = $this->createForm(CategoryFormType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Category updated.');

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/categories/form.html.twig', [
            'form'  => $form,
            'title' => 'Edit category',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_categories_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Categories $category): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_category_'.$category->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->bookCategoryRepository->count(['category' => $category]) > 0) {
            $this->addFlash('error', 'Cannot delete a category that is still linked to books.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $this->em->remove($category);
        $this->em->flush();
        $this->addFlash('success', 'Category deleted.');

        return $this->redirectToRoute('admin_categories_index');
    }
}
