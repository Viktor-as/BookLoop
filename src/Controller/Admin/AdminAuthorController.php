<?php

namespace App\Controller\Admin;

use App\Entity\Authors;
use App\Form\Admin\AuthorFormType;
use App\Repository\AuthorBookRepository;
use App\Repository\AuthorsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/authors')]
#[IsGranted('ROLE_ADMIN')]
final class AdminAuthorController extends AbstractController
{
    public function __construct(
        private readonly AuthorsRepository $authorsRepository,
        private readonly AuthorBookRepository $authorBookRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_authors_index', methods: ['GET'])]
    public function index(): Response
    {
        $authors = $this->authorsRepository->findBy(
            [],
            ['updatedAt' => 'DESC', 'lastName' => 'ASC', 'firstName' => 'ASC'],
        );

        return $this->render('admin/authors/index.html.twig', ['authors' => $authors]);
    }

    #[Route('/new', name: 'admin_authors_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $author = new Authors();
        $form    = $this->createForm(AuthorFormType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($author);
            $this->em->flush();
            $this->addFlash('success', 'Author created.');

            return $this->redirectToRoute('admin_authors_index');
        }

        return $this->render('admin/authors/form.html.twig', [
            'form'  => $form,
            'title' => 'New author',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_authors_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Authors $author): Response
    {
        $form = $this->createForm(AuthorFormType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Author updated.');

            return $this->redirectToRoute('admin_authors_index');
        }

        return $this->render('admin/authors/form.html.twig', [
            'form'  => $form,
            'title' => 'Edit author',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_authors_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Authors $author): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_author_'.$author->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->authorBookRepository->count(['author' => $author]) > 0) {
            $this->addFlash('error', 'Cannot delete an author who is still linked to books.');

            return $this->redirectToRoute('admin_authors_index');
        }

        $this->em->remove($author);
        $this->em->flush();
        $this->addFlash('success', 'Author deleted.');

        return $this->redirectToRoute('admin_authors_index');
    }
}
