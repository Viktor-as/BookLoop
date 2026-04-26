<?php

namespace App\Api;

/**
 * RFC 7807-style problem plus stable machine "code" for clients.
 */
final readonly class ApiProblem
{
    /**
     * @param list<array{field: string, message: string}>|null $violations
     */
    public function __construct(
        public int $status,
        public string $code,
        public string $title,
        public string $detail,
        public string $type = 'about:blank',
        public ?array $violations = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'code' => $this->code,
        ];
        if ($this->violations !== null) {
            $data['violations'] = $this->violations;
        }

        return $data;
    }
}
