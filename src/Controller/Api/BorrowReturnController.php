<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\Response\BorrowingItemResponse;
use App\Dto\Response\BorrowReturnSuccessResponse;
use App\Dto\ReturnBookResult;
use App\Repository\BorrowsRepository;
use App\Service\ReturnBookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BorrowReturnController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ReturnBookService $returnBookService,
        private readonly BorrowsRepository $borrowsRepository,
    ) {}

    #[Route(
        '/api/borrows/{id}/return',
        name: 'api_borrows_return',
        methods: ['POST'],
        requirements: ['id' => '\d+'],
    )]
    public function __invoke(int $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $result = $this->returnBookService->returnForMember($user, $id);
        if (!$result->ok) {
            return $this->jsonProblem($this->apiProblemForReturnResult($result));
        }

        $row = $this->borrowsRepository->findBorrowHistoryCatalogRowByIdForMember(
            $id,
            (int) $user->getId(),
        );

        $payload = new BorrowReturnSuccessResponse(
            message: $result->message,
            item: $row !== null ? BorrowingItemResponse::fromRow($row) : null,
        );

        return $this->json($payload, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    private function apiProblemForReturnResult(ReturnBookResult $r): ApiProblem
    {
        $code = (string) ($r->errorCode ?? 'unknown');

        return match ($code) {
            'borrow_not_found' => new ApiProblem(
                status: Response::HTTP_NOT_FOUND,
                code: 'borrow_not_found',
                title: 'Borrow not found',
                detail: $r->message,
            ),
            'return_forbidden' => new ApiProblem(
                status: Response::HTTP_FORBIDDEN,
                code: 'return_forbidden',
                title: 'Not allowed to return this loan',
                detail: $r->message,
            ),
            'already_returned' => new ApiProblem(
                status: Response::HTTP_CONFLICT,
                code: 'already_returned',
                title: 'Already returned',
                detail: $r->message,
            ),
            default => new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'return_failed',
                title: 'Return request failed',
                detail: $r->message,
            ),
        };
    }
}
