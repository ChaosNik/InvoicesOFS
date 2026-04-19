<?php

namespace App\Services\Ofs;

use App\Models\Invoice;
use App\Models\OfsFiscalization;
use App\Services\Ofs\Contracts\OfsClient;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfsFiscalizationService
{
    public function __construct(
        private readonly OfsClient $client,
        private readonly OfsInvoiceMapper $mapper
    ) {
    }

    public function fiscalizeInvoice(Invoice $invoice, ?Authenticatable $cashier = null): OfsFiscalization
    {
        if ($invoice->fiscal_status === OfsFiscalization::STATUS_FISCALIZED) {
            throw new OfsValidationException('This invoice has already been fiscalized by OFS.');
        }

        $attempt = OfsFiscalization::create([
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'request_id' => (string) Str::uuid(),
            'status' => OfsFiscalization::STATUS_PENDING,
            'driver' => config('ofs.driver'),
        ]);

        $invoice->forceFill([
            'fiscal_status' => OfsFiscalization::STATUS_PENDING,
        ])->save();

        try {
            $payload = $this->mapper->map($invoice, $cashier);

            $attempt->forceFill([
                'request_payload' => $payload,
            ])->save();

            $response = $this->client->fiscalize($payload, $attempt->request_id);
        } catch (OfsException $exception) {
            $attempt->forceFill([
                'status' => OfsFiscalization::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'error_payload' => $exception->context,
            ])->save();

            $invoice->forceFill([
                'fiscal_status' => OfsFiscalization::STATUS_FAILED,
            ])->save();

            throw $exception;
        }

        return DB::transaction(function () use ($invoice, $attempt, $response) {
            $fiscalizedAt = $this->parseFiscalizedAt($response['sdcDateTime'] ?? null);
            $fiscalInvoiceNumber = $response['invoiceNumber'] ?? null;
            $verificationUrl = $response['verificationUrl'] ?? null;
            $totalAmount = $response['totalAmount'] ?? null;

            $attempt->forceFill([
                'status' => OfsFiscalization::STATUS_FISCALIZED,
                'response_payload' => $response,
                'fiscal_invoice_number' => $fiscalInvoiceNumber,
                'sdc_date_time' => $fiscalizedAt,
                'verification_url' => $verificationUrl,
                'total_amount' => $totalAmount,
            ])->save();

            $invoice->forceFill([
                'fiscal_status' => OfsFiscalization::STATUS_FISCALIZED,
                'fiscal_invoice_number' => $fiscalInvoiceNumber,
                'fiscalized_at' => $fiscalizedAt,
                'fiscal_verification_url' => $verificationUrl,
            ])->save();

            return $attempt;
        });
    }

    private function parseFiscalizedAt(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }
}
