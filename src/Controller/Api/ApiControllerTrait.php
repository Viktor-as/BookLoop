<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Entity\Users;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @method Users|null getUser()
 */
trait ApiControllerTrait
{
    private function jsonProblem(ApiProblem $problem, ?int $httpStatus = null): JsonResponse
    {
        $status = $httpStatus ?? $problem->status;

        return new JsonResponse(
            $problem->toArray(),
            $status,
            ['Content-Type' => 'application/problem+json; charset=UTF-8'],
        );
    }

    private function requireUser(): Users|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Users) {
            return $this->jsonProblem(new ApiProblem(
                status: Response::HTTP_UNAUTHORIZED,
                code: 'authentication_required',
                title: 'Authentication required',
                detail: 'You must be signed in to access this resource.',
            ));
        }

        return $user;
    }

    private function apiProblemFromViolations(ConstraintViolationListInterface $violations): ApiProblem
    {
        $list = [];
        foreach ($violations as $v) {
            $list[] = [
                'field' => (string) $v->getPropertyPath(),
                'message' => $v->getMessage(),
            ];
        }

        return new ApiProblem(
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            code: 'validation_error',
            title: 'Validation failed',
            detail: 'One or more fields are not valid.',
            violations: $list,
        );
    }
}
