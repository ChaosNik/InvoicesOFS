<?php

namespace App\Services\Ofs;

use App\Services\Ofs\Contracts\OfsClient;
use Carbon\Carbon;

class FakeOfsClient implements OfsClient
{
    public function fiscalize(array $payload, string $requestId): array
    {
        $invoiceRequest = $payload['invoiceRequest'] ?? [];
        $totalAmount = collect($invoiceRequest['payment'] ?? [])->sum('amount');
        $hash = strtoupper(substr(hash('sha256', $requestId), 0, 8));
        $prefix = ($invoiceRequest['transactionType'] ?? null) === 'Refund'
            ? 'FAKE-OFS-REFUND-'
            : 'FAKE-OFS-';
        $sdcDateTime = Carbon::create(2026, 1, 1, 0, 0, 0, 'UTC')
            ->addSeconds(hexdec(substr($hash, 0, 6)) % 86400)
            ->toIso8601String();
        $taxItems = collect($invoiceRequest['items'] ?? [])
            ->flatMap(fn (array $item) => $item['labels'] ?? [])
            ->unique()
            ->values()
            ->map(fn (string $label) => [
                'label' => $label,
                'amount' => 0.0,
            ])
            ->all();

        return [
            'invoiceNumber' => $prefix.$hash,
            'sdcDateTime' => $sdcDateTime,
            'verificationUrl' => 'https://fake.ofs.local/verify/'.$hash,
            'totalAmount' => round($totalAmount, 2),
            'messages' => 'Fake OFS fiscalization successful',
            'taxItems' => $taxItems,
        ];
    }

    public function findInvoiceByRequestId(string $requestId): ?array
    {
        return null;
    }

    public function probe(): array
    {
        return [
            'driver' => 'fake',
            'attention' => 'ok',
            'status' => [
                'uid' => 'FAKE-OFS',
                'sdcDateTime' => Carbon::now()->toIso8601String(),
            ],
        ];
    }
}
