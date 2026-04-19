<?php

namespace App\Services\Ofs;

use App\Models\Invoice;
use App\Models\Tax;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class OfsInvoiceMapper
{
    public function map(Invoice $invoice, ?Authenticatable $cashier = null): array
    {
        $invoice->loadMissing([
            'customer',
            'items.taxes.taxType',
            'taxes.taxType',
            'fiscalPaymentMethod',
            'creator',
            'originalInvoice',
        ]);

        $cashierName = $cashier?->name ?? $invoice->creator?->name;

        if (! $cashierName) {
            throw new OfsValidationException('OFS requires a cashier name.');
        }

        if (! $invoice->fiscalPaymentMethod?->ofs_payment_type) {
            throw new OfsValidationException('Please choose a payment mode with an OFS payment type before fiscalizing this invoice.');
        }

        $items = $invoice->items->map(fn ($item) => [
            'name' => $item->name,
            'gtin' => $this->gtin($item->ofs_gtin, $item->name),
            'labels' => $this->labelsForItem($invoice, $item->taxes),
            'totalAmount' => $this->money($item->total),
            'unitPrice' => $this->money($item->price),
            'quantity' => round((float) $item->quantity, 3),
        ])->values()->all();

        $transactionType = $invoice->isCreditNote() ? 'Refund' : 'Sale';

        $invoiceRequest = [
            'invoiceType' => 'Normal',
            'transactionType' => $transactionType,
            'payment' => [[
                'amount' => $this->money($invoice->total),
                'paymentType' => $invoice->fiscalPaymentMethod->ofs_payment_type,
            ]],
            'items' => $items,
            'cashier' => $cashierName,
        ];

        if ($invoice->isCreditNote()) {
            if (! $invoice->referent_document_number || ! $invoice->referent_document_dt) {
                throw new OfsValidationException('OFS refund requires the original fiscal receipt number and fiscal date.');
            }

            $invoiceRequest['referentDocumentNumber'] = $invoice->referent_document_number;
            $invoiceRequest['referentDocumentDT'] = $invoice->referent_document_dt->toIso8601String();
        }

        if ($invoice->customer?->tax_id) {
            $invoiceRequest['buyerId'] = $invoice->customer->tax_id;
        }

        $payload = [
            'print' => (bool) config('ofs.print'),
            'invoiceRequest' => $invoiceRequest,
        ];

        if (config('ofs.render_receipt_image')) {
            $payload['renderReceiptImage'] = true;
            $payload['receiptImageFormat'] = config('ofs.receipt_image_format', 'Png');
        }

        return $payload;
    }

    private function gtin(?string $gtin, string $itemName): string
    {
        $gtin = trim((string) $gtin);

        if (strlen($gtin) < 8 || strlen($gtin) > 14) {
            throw new OfsValidationException("OFS GTIN for item \"{$itemName}\" must be 8 to 14 characters.");
        }

        return $gtin;
    }

    private function labelsForItem(Invoice $invoice, Collection $itemTaxes): array
    {
        $usesItemTaxes = in_array($invoice->tax_per_item, ['YES', true, 1, '1'], true);
        $taxes = ($usesItemTaxes || $invoice->taxes->isEmpty()) ? $itemTaxes : $invoice->taxes;

        $labels = $taxes
            ->filter(fn (Tax $tax) => (int) $tax->tax_type_id > 0)
            ->map(function (Tax $tax) {
                $label = trim((string) $tax->taxType?->ofs_label);

                if ($label === '') {
                    throw new OfsValidationException("OFS label is missing for tax type \"{$tax->name}\".");
                }

                return $label;
            })
            ->unique()
            ->values()
            ->all();

        if ($labels === []) {
            throw new OfsValidationException('At least one OFS tax label is required for every fiscalized invoice item.');
        }

        return $labels;
    }

    private function money(int|float|null $amount): float
    {
        return round(((float) $amount) / 100, 2);
    }
}
