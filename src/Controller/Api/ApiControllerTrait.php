<?php

namespace App\Controller\Api;

use App\Api\ApiProblem;
use App\Api\ApiProblemFactory;
use App\Entity\Users;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

        $response = new JsonResponse(
            $problem->toArray(),
            $status,
            ['Content-Type' => 'application/problem+json; charset=UTF-8'],
        );

        return $this->applyNoStore($response);
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
        return ApiProblemFactory::fromViolations($violations);
    }

    private function applyNoStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function applyPrivateRevalidation(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    private function applyEtagAndHandleConditional(Request $request, Response $response, string $etag): Response
    {
        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }
}
