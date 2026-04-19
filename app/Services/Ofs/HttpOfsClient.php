<?php

namespace App\Services\Ofs;

use App\Services\Ofs\Contracts\OfsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HttpOfsClient implements OfsClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout
    ) {
    }

    public function fiscalize(array $payload, string $requestId): array
    {
        try {
            $response = $this->request($requestId)->post($this->url('/api/invoices'), $payload);
        } catch (ConnectionException $exception) {
            $issuedInvoice = $this->findInvoiceByRequestId($requestId);

            if ($issuedInvoice !== null) {
                return $issuedInvoice;
            }

            throw new OfsException('OFS fiscalization request could not reach the ESIR device.', [
                'request_id' => $requestId,
            ], 0, $exception);
        }

        if ($response->successful()) {
            return $this->jsonResponse($response);
        }

        throw new OfsException($this->errorMessage($response), [
            'status' => $response->status(),
            'body' => $response->json() ?? $response->body(),
        ]);
    }

    public function findInvoiceByRequestId(string $requestId): ?array
    {
        try {
            $response = $this->request()->get($this->url('/api/invoices/request/'.$requestId));
        } catch (ConnectionException) {
            return null;
        }

        if ($response->status() === 204 || trim($response->body()) === '') {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->jsonResponse($response);
    }

    public function probe(): array
    {
        try {
            $attention = $this->request()->get($this->url('/api/attention'));
        } catch (ConnectionException $exception) {
            throw new OfsException('OFS ESIR device is not reachable.', [], 0, $exception);
        }

        if (! $attention->successful()) {
            throw new OfsException($this->errorMessage($attention), [
                'status' => $attention->status(),
                'body' => $attention->json() ?? $attention->body(),
            ]);
        }

        try {
            $status = $this->request()->get($this->url('/api/status'));
        } catch (ConnectionException $exception) {
            throw new OfsException('OFS ESIR device status could not be read.', [], 0, $exception);
        }

        if (! $status->successful()) {
            throw new OfsException($this->errorMessage($status), [
                'status' => $status->status(),
                'body' => $status->json() ?? $status->body(),
            ]);
        }

        return [
            'driver' => 'http',
            'attention' => 'ok',
            'status' => $status->json() ?? [],
        ];
    }

    private function request(?string $requestId = null)
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json; charset=UTF-8',
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        if ($requestId) {
            $headers['RequestId'] = $requestId;
        }

        return Http::timeout($this->timeout)->withHeaders($headers);
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function jsonResponse(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function errorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json['message'] ?? $json['error'] ?? 'OFS ESIR returned an error.';
        }

        return $response->body() ?: 'OFS ESIR returned an error.';
    }
}
