<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Dto\BorrowBookRequest;
use App\Dto\BorrowBookResult;
use App\Entity\Users;
use App\Service\BorrowBookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BookBorrowController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly BorrowBookService $borrowBookService,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route(
        '/api/books/{slug}/borrow',
        name: 'api_books_borrow',
        methods: ['POST'],
        requirements: ['slug' => '[a-z0-9\-]+'],
    )]
    public function __invoke(string $slug, Request $request): Response
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        // @var Users $user
        $data = $this->decodeRequestJson($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $payloadErrors = $this->validator->validate($data, new Assert\Collection(
            fields: [
                'days' => [
                    new Assert\NotNull(message: 'Field "days" is required.'),
                    new Assert\Positive(message: 'Field "days" must be a positive number.'),
                ],
            ],
            allowExtraFields: false,
        ));
        if ($payloadErrors->count() > 0) {
            return $this->jsonProblem($this->apiProblemFromViolations($payloadErrors));
        }

        $input   = $this->buildBorrowRequestFromData($data);
        $errors  = $this->validator->validate($input);
        if ($errors->count() > 0) {
            return $this->jsonProblem($this->apiProblemFromViolations($errors));
        }

        $result = $this->borrowBookService->borrow($user, $slug, (int) $input->days);

        if (!$result->ok) {
            return $this->jsonProblem($this->apiProblemForBorrowResult($result));
        }

        $location = $this->generateUrl(
            'api_borrows_get',
            ['id' => $result->borrowId],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $payload = [
            'message'  => $result->message,
            'borrowId' => $result->borrowId,
            'dueDate'  => $result->dueDate?->format(\DateTimeInterface::ATOM),
        ];

        return $this->json(
            $payload,
            Response::HTTP_CREATED,
            [
                'Location' => $location,
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
        );
    }

    private function decodeRequestJson(Request $request): array|JsonResponse
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_json',
                title: 'Invalid request body',
                detail: 'Send a JSON object with a "days" field (e.g. {"days": 14}).',
            ));
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_json',
                title: 'Invalid JSON',
                detail: 'The request body is not valid JSON.',
            ));
        }

        if (!\is_array($data)) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_BAD_REQUEST,
                code: 'invalid_json',
                title: 'Invalid request body',
                detail: 'The JSON value must be an object.',
            ));
        }

        return $data;
    }

    private function buildBorrowRequestFromData(array $data): BorrowBookRequest
    {
        $input = new BorrowBookRequest();
        if (array_key_exists('days', $data)) {
            $raw = $data['days'];
            if (is_int($raw)) {
                $input->days = $raw;
            } elseif (is_float($raw) && fmod($raw, 1.0) == 0.0) {
                $input->days = (int) $raw;
            } elseif (is_string($raw) && preg_match('/^[-+]?\d+$/', trim($raw, " \t\n\r\0\x0B"))) {
                $input->days = (int) ltrim($raw, '+');
            } elseif (is_string($raw) && is_numeric($raw)) {
                $input->days = (int) (float) $raw;
            } else {
                $input->days = 0;
            }
        } else {
            $input->days = null;
        }

        return $input;
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
