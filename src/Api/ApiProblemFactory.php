<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Builds {@see ApiProblem} instances for common API failure shapes.
 *
 * Used by both controllers (when they detect domain-level problems) and the
 * {@see \App\EventListener\ApiProblemExceptionListener} (when Symfony itself
 * raises an exception during request mapping).
 */
final class ApiProblemFactory
{
    public static function fromViolations(
        ConstraintViolationListInterface $violations,
        int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
    ): ApiProblem {
        $list = [];
        foreach ($violations as $v) {
            $list[] = [
                'field'   => self::fieldFromPath((string) $v->getPropertyPath()),
                'message' => (string) $v->getMessage(),
            ];
        }

        $detail = self::summaryDetailForViolationItems($list);

        return new ApiProblem(
            status: $status,
            code: 'validation_error',
            title: 'Validation failed',
            detail: $detail,
            violations: $list,
        );
    }

    /**
     * @param list<string> $extraAttributes
     */
    public static function fromExtraAttributes(array $extraAttributes): ApiProblem
    {
        $list = [];
        foreach ($extraAttributes as $field) {
            $list[] = [
                'field'   => (string) $field,
                'message' => 'This field is not allowed.',
            ];
        }

        $detail = self::summaryDetailForViolationItems($list);

        return new ApiProblem(
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            code: 'validation_error',
            title: 'Validation failed',
            detail: $detail,
            violations: $list,
        );
    }

    public static function invalidJson(?string $detail = null): ApiProblem
    {
        return new ApiProblem(
            status: Response::HTTP_BAD_REQUEST,
            code: 'invalid_json',
            title: 'Invalid request body',
            detail: $detail !== null && $detail !== ''
                ? $detail
                : 'The request body is not valid JSON.',
        );
    }

    public static function unsupportedMediaType(?string $detail = null): ApiProblem
    {
        return new ApiProblem(
            status: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            code: 'unsupported_media_type',
            title: 'Unsupported media type',
            detail: $detail !== null && $detail !== ''
                ? $detail
                : 'The request payload format is not supported.',
        );
    }

    /**
     * Strips wrapping brackets that Symfony adds for nested property paths
     * (e.g. "[email]" -> "email"). For DTO-mapped payloads the path is already
     * a plain field name; this is a safety net.
     */
    private static function fieldFromPath(string $path): string
    {
        if ($path === '') {
            return $path;
        }
        if (str_starts_with($path, '[') && str_ends_with($path, ']')) {
            $path = substr($path, 1, -1);
        }
        if (str_contains($path, '.')) {
            $path = substr($path, (int) strrpos($path, '.') + 1);
        }
        if (str_contains($path, '[')) {
            $path = substr($path, 0, (int) strpos($path, '['));
        }

        return $path;
    }

    /**
     * @param list<array{field: string, message: string}> $items
     */
    private static function summaryDetailForViolationItems(array $items): string
    {
        $names = [];
        foreach ($items as $row) {
            $f = (string) ($row['field'] ?? '');
            if ($f === '') {
                continue;
            }
            $names[$f] = true;
        }
        if ($names === []) {
            return 'One or more fields are not valid.';
        }
        $sorted = array_keys($names);
        sort($sorted, \SORT_STRING);

        return 'Check these fields: '.implode(', ', $sorted).'.';
    }
}
