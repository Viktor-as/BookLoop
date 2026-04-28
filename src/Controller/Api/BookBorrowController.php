<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\Request\BorrowBookRequest;
use App\Dto\Response\BorrowSuccessResponse;
use App\Dto\BorrowBookResult;
use App\Entity\Users;
use App\Service\BorrowBookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class BookBorrowController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly BorrowBookService $borrowBookService,
    ) {}

    #[Route('/api/v1/borrows', name: 'api_v1_borrows_create', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractObjectNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        BorrowBookRequest $input,
        #[CurrentUser] Users $user,
    ): Response {
        $result = $this->borrowBookService->borrow($user, (string) $input->bookSlug, (int) $input->days);

        if (!$result->ok) {
            return $this->jsonProblem($this->apiProblemForBorrowResult($result));
        }

        $location = $this->generateUrl(
            'api_v1_borrows_get',
            ['id' => $result->borrowId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $payload = new BorrowSuccessResponse(
            message: $result->message,
            borrowId: (int) $result->borrowId,
            dueDate: $result->dueDate?->format(\DateTimeInterface::ATOM),
        );

        return $this->json(
            $payload,
            Response::HTTP_CREATED,
            [
                'Location' => $location,
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        );
    }

    private function apiProblemForBorrowResult(BorrowBookResult $r): ApiProblem
    {
        $code = (string) ($r->errorCode ?? 'unknown');

        return match ($code) {
            'not_found' => new ApiProblem(
                status: Response::HTTP_NOT_FOUND,
                code: 'not_found',
                title: 'Book not found',
                detail: $r->message,
            ),
            'already_borrowed' => new ApiProblem(
                status: Response::HTTP_CONFLICT,
                code: 'already_borrowed',
                title: 'You already have this book on loan',
                detail: $r->message,
            ),
            'on_loan' => new ApiProblem(
                status: Response::HTTP_CONFLICT,
                code: 'on_loan',
                title: 'Book not available',
                detail: $r->message,
            ),
            'borrow_limit' => new ApiProblem(
                status: Response::HTTP_CONFLICT,
                code: 'borrow_limit',
                title: 'Borrow limit reached',
                detail: $r->message,
            ),
            'invalid_days' => new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_days',
                title: 'Invalid loan period',
                detail: $r->message,
            ),
            default => new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'borrow_failed',
                title: 'Borrow request failed',
                detail: $r->message,
            ),
        };
    }
}
