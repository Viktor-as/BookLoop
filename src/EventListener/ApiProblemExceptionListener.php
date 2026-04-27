<?php

namespace App\EventListener;

use App\Api\ApiProblem;
use App\Api\ApiProblemFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Converts framework-level exceptions raised on `/api/*` routes into uniform
 * {@see ApiProblem} JSON responses. Controllers keep raising or returning
 * problems for their own domain errors; this listener handles failures that
 * happen before/around the controller call (request payload mapping, malformed
 * JSON, unsupported media types, ...).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final class ApiProblemExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $problem = $this->buildProblem($event->getThrowable());
        if ($problem === null) {
            return;
        }

        $event->setResponse(new JsonResponse(
            $problem->toArray(),
            $problem->status,
            ['Content-Type' => 'application/problem+json; charset=UTF-8'],
        ));
    }

    private function buildProblem(\Throwable $throwable): ?ApiProblem
    {
        if ($throwable instanceof HttpException) {
            $e = $this->findInChain($throwable, ValidationFailedException::class);
            if ($e !== null) {
                return ApiProblemFactory::fromViolations(
                    $e->getViolations(),
                    $throwable->getStatusCode(),
                );
            }
        }

        if ($throwable instanceof ExtraAttributesException) {
            return ApiProblemFactory::fromExtraAttributes($throwable->getExtraAttributes());
        }

        if ($throwable instanceof BadRequestHttpException) {
            return ApiProblemFactory::invalidJson($throwable->getMessage());
        }

        if ($throwable instanceof UnsupportedMediaTypeHttpException) {
            return ApiProblemFactory::unsupportedMediaType($throwable->getMessage());
        }

        return null;
    }

    /**
     * @template T of \Throwable
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function findInChain(\Throwable $e, string $class)
    {
        for ($c = $e; $c !== null; $c = $c->getPrevious()) {
            if (is_a($c, $class, false)) {
                return $c;
            }
        }

        return null;
    }
}
