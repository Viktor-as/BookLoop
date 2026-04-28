<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\BorrowBookResult;
use App\Dto\Request\BorrowBookRequest;
use App\Dto\Request\BorrowPatchRequest;
use App\Dto\Response\BorrowingItemResponse;
use App\Dto\Response\BorrowReturnSuccessResponse;
use App\Dto\Response\BorrowSuccessResponse;
use App\Dto\ReturnBookResult;
use App\Entity\Users;
use App\Repository\BorrowsRepository;
use App\Service\BorrowBookService;
use App\Service\ReturnBookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

final class BorrowController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly BorrowBookService $borrowBookService,
        private readonly ReturnBookService $returnBookService,
        private readonly BorrowsRepository $borrowsRepository,
    ) {}

    #[Route('/api/v1/borrows', name: 'api_v1_borrows_create', methods: ['POST'])]
    public function create(
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

        $response = $this->json(
            $payload,
            Response::HTTP_CREATED,
            [
                'Location' => $location,
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        );

        return $this->applyNoStore($response);
    }

    #[Route('/api/v1/borrows/{id}', name: 'api_v1_borrows_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $row = $this->borrowsRepository->findBorrowHistoryCatalogRowByIdForMember(
            $id,
            (int) $user->getId(),
        );
        if ($row === null) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_NOT_FOUND,
                code: 'borrow_not_found',
                title: 'Borrow not found',
                detail: 'The requested loan was not found or is not part of your history.',
            ));
        }

        $response = $this->json(BorrowingItemResponse::fromRow($row));

        return $this->applyNoStore($response);
    }

    #[Route('/api/v1/borrows/{id}', name: 'api_v1_borrows_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patch(
        int $id,
        #[MapRequestPayload(
            acceptFormat: 'json',
            serializationContext: [AbstractObjectNormalizer::ALLOW_EXTRA_ATTRIBUTES => false],
        )]
        BorrowPatchRequest $input,
    ): JsonResponse {
        if ($input->returned !== true) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_patch',
                title: 'Invalid borrow update',
                detail: 'Only {"returned": true} is currently supported on this endpoint.',
            ));
        }

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

        $response = $this->json($payload, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);

        return $this->applyNoStore($response);
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
