<?php

namespace App\Services\Ofs\Contracts;

interface OfsClient
{
    public function fiscalize(array $payload, string $requestId): array;

    public function findInvoiceByRequestId(string $requestId): ?array;

    public function probe(): array;
}
