<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\BooksRepository;
use App\Service\BorrowQuotaPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class BookController extends AbstractController
{
    public function __construct(
        private readonly BooksRepository $booksRepository,
        private readonly BorrowQuotaPresenter $borrowQuotaPresenter,
    ) {}

    #[Route(
        '/books/{slug}',
        name: 'book_show',
        methods: ['GET'],
        requirements: ['slug' => '[a-z0-9\-]+'],
    )]
    public function show(string $slug): Response
    {
        $book = $this->booksRepository->findCatalogDetailBySlug($slug);
        if ($book === null) {
            throw new NotFoundHttpException(sprintf('No book found for slug "%s".', $slug));
        }

        $quota = null;
        $user  = $this->getUser();
        if ($user instanceof Users && $book['available']) {
            $quota = $this->borrowQuotaPresenter->forMemberAndBook($user, $book['borrowDaysLimit']);
        }

        return $this->render('book/show.html.twig', [
            'book'  => $book,
            'quota' => $quota,
        ]);
    }
}
