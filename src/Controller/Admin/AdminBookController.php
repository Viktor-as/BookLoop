<?php

namespace App\Controller\Admin;

use App\Entity\AuthorBook;
use App\Entity\BookCategory;
use App\Entity\Books;
use App\Entity\Users;
use App\Form\Admin\BookAdminFormType;
use App\Repository\AuthorBookRepository;
use App\Repository\BookCategoryRepository;
use App\Repository\BooksRepository;
use App\Repository\BorrowsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/books')]
#[IsGranted('ROLE_ADMIN')]
final class AdminBookController extends AbstractController
{
    public function __construct(
        private readonly BooksRepository $booksRepository,
        private readonly AuthorBookRepository $authorBookRepository,
        private readonly BookCategoryRepository $bookCategoryRepository,
        private readonly BorrowsRepository $borrowsRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'admin_books_index', methods: ['GET'])]
    public function index(): Response
    {
        $books = $this->booksRepository->findBy(
            [],
            ['updatedAt' => 'DESC', 'title' => 'ASC'],
        );

        return $this->render('admin/books/index.html.twig', ['books' => $books]);
    }

    #[Route('/new', name: 'admin_books_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $book = new Books();
        $form  = $this->createForm(BookAdminFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($book);
            $this->em->flush();

            $admin = $this->getUser();
            if (!$admin instanceof Users) {
                throw $this->createAccessDeniedException();
            }

            $this->syncBookRelations(
                $book,
                $form->get('authors')->getData() ?? [],
                $form->get('categories')->getData() ?? [],
                $admin,
            );
            $this->em->flush();
            $this->addFlash('success', 'Book created.');

            return $this->redirectToRoute('admin_books_index');
        }

        return $this->render('admin/books/form.html.twig', [
            'form'  => $form,
            'title' => 'New book',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_books_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Books $book): Response
    {
        $form = $this->createForm(BookAdminFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $admin = $this->getUser();
            if (!$admin instanceof Users) {
                throw $this->createAccessDeniedException();
            }

            $this->syncBookRelations(
                $book,
                $form->get('authors')->getData() ?? [],
                $form->get('categories')->getData() ?? [],
                $admin,
            );
            $this->em->flush();
            $this->addFlash('success', 'Book updated.');

            return $this->redirectToRoute('admin_books_index');
        }

        return $this->render('admin/books/form.html.twig', [
            'form'  => $form,
            'title' => 'Edit book',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_books_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Books $book): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_book_'.$book->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($this->borrowsRepository->countBorrowsForBook((int) $book->getId()) > 0) {
            $this->addFlash('error', 'Cannot delete a book that has borrow history. Remove or archive borrows first.');

            return $this->redirectToRoute('admin_books_index');
        }

        foreach ($this->authorBookRepository->findBy(['book' => $book]) as $ab) {
            $this->em->remove($ab);
        }
        foreach ($this->bookCategoryRepository->findBy(['book' => $book]) as $bc) {
            $this->em->remove($bc);
        }
        $this->em->remove($book);
        $this->em->flush();
        $this->addFlash('success', 'Book deleted.');

        return $this->redirectToRoute('admin_books_index');
    }

    /**
     * Upsert junction rows: add new links first, then remove stale ones so a book is never
     * left with zero authors/categories during an update (and we avoid DELETE triggers that
     * would fight CASCADE / admin delete flows on MySQL).
     *
     * @param iterable<int, \App\Entity\Authors>     $authors
     * @param iterable<int, \App\Entity\Categories> $categories
     */
    private function syncBookRelations(Books $book, iterable $authors, iterable $categories, Users $admin): void
    {
        $authorsArr     = $authors instanceof \Traversable ? iterator_to_array($authors) : (array) $authors;
        $categoriesArr = $categories instanceof \Traversable ? iterator_to_array($categories) : (array) $categories;

        if ($authorsArr === [] || $categoriesArr === []) {
            throw new \InvalidArgumentException('A book must have at least one author and one category.');
        }

        $this->syncAuthorBooks($book, $authorsArr, $admin);
        $this->syncBookCategories($book, $categoriesArr, $admin);
    }

    /**
     * @param list<\App\Entity\Authors> $authors
     */
    private function syncAuthorBooks(Books $book, array $authors, Users $admin): void
    {
        $selectedByAuthorId = [];
        foreach ($authors as $author) {
            $selectedByAuthorId[(int) $author->getId()] = $author;
        }

        $existingByAuthorId = [];
        foreach ($this->authorBookRepository->findBy(['book' => $book]) as $ab) {
            $aid = (int) $ab->getAuthor()?->getId();
            if ($aid > 0) {
                $existingByAuthorId[$aid] = $ab;
            }
        }

        foreach ($selectedByAuthorId as $authorId => $author) {
            if (!isset($existingByAuthorId[$authorId])) {
                $this->em->persist(
                    (new AuthorBook())
                        ->setBook($book)
                        ->setAuthor($author)
                        ->setUpdatedBy($admin),
                );
            }
        }

        foreach ($existingByAuthorId as $authorId => $ab) {
            if (!isset($selectedByAuthorId[$authorId])) {
                $this->em->remove($ab);
            }
        }
    }

    /**
     * @param list<\App\Entity\Categories> $categories
     */
    private function syncBookCategories(Books $book, array $categories, Users $admin): void
    {
        $selectedByCategoryId = [];
        foreach ($categories as $category) {
            $selectedByCategoryId[(int) $category->getId()] = $category;
        }

        $existingByCategoryId = [];
        foreach ($this->bookCategoryRepository->findBy(['book' => $book]) as $bc) {
            $cid = (int) $bc->getCategory()?->getId();
            if ($cid > 0) {
                $existingByCategoryId[$cid] = $bc;
            }
        }

        foreach ($selectedByCategoryId as $categoryId => $category) {
            if (!isset($existingByCategoryId[$categoryId])) {
                $this->em->persist(
                    (new BookCategory())
                        ->setBook($book)
                        ->setCategory($category)
                        ->setUpdatedBy($admin),
                );
            }
        }

        foreach ($existingByCategoryId as $categoryId => $bc) {
            if (!isset($selectedByCategoryId[$categoryId])) {
                $this->em->remove($bc);
            }
        }
    }
}
