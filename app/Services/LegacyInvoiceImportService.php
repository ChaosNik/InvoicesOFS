<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\OfsFiscalization;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class LegacyInvoiceImportService
{
    public function import(string $path, User $user, int $companyId): array
    {
        [$rows, $headers] = $this->readImportCsv($path);
        $this->validateImportHeaders($headers);

        $result = [
            'created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($this->groupRowsByInvoiceNumber($rows) as $invoiceNumber => $group) {
            try {
                DB::transaction(function () use ($group, $invoiceNumber, $user, $companyId) {
                    $this->importInvoiceGroup($invoiceNumber, $group, $user, $companyId);
                });

                $result['created']++;
            } catch (\Throwable $exception) {
                $result['skipped']++;
                $result['errors'][] = [
                    'invoice_number' => $invoiceNumber,
                    'row' => $group['row'],
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function importInvoiceGroup(string $invoiceNumber, array $group, User $user, int $companyId): void
    {
        $firstRow = $group['rows'][array_key_first($group['rows'])];

        if (Invoice::where('company_id', $companyId)->where('invoice_number', $invoiceNumber)->exists()) {
            throw new \RuntimeException('Invoice number already exists.');
        }

        $currencyId = $this->resolveCurrencyId($firstRow['currency_code'] ?? null, $companyId);
        $exchangeRate = $this->parseDecimal($firstRow['exchange_rate'] ?? null, 1);
        $customer = $this->resolveCustomer($firstRow, $user, $companyId, $currencyId);
        $paymentMethod = $this->resolvePaymentMethod($firstRow, $companyId);

        $items = collect($group['rows'])->map(function (array $row) use ($companyId, $exchangeRate) {
            return $this->buildInvoiceItemPayload($row, $companyId, $exchangeRate);
        });

        $subTotal = $this->resolveMoneyValue(
            $firstRow['sub_total'] ?? null,
            $items->sum('total')
        );
        $taxTotal = $this->resolveMoneyValue($firstRow['tax_total'] ?? null, 0);
        $discountType = $this->normalizeDiscountType($firstRow['discount_type'] ?? null);
        $discountValue = $discountType === 'percentage'
            ? (int) round($this->parseDecimal($firstRow['discount_value'] ?? null, 0) ?? 0)
            : $this->resolveMoneyValue($firstRow['discount_value'] ?? null, 0);
        $discountAmount = $discountType === 'percentage'
            ? (int) round(($subTotal * $discountValue) / 100)
            : $discountValue;
        $total = $this->resolveMoneyValue(
            $firstRow['total'] ?? null,
            max(0, $subTotal - $discountAmount) + $taxTotal
        );
        $dueAmount = $this->resolveMoneyValue(
            $firstRow['due_amount'] ?? null,
            $total
        );

        $paidStatus = $this->normalizePaidStatus($firstRow['paid_status'] ?? null, $dueAmount, $total);
        $status = $this->normalizeInvoiceStatus($firstRow['status'] ?? null, $paidStatus, $dueAmount, $total);
        $invoiceDate = $this->parseDate($firstRow['invoice_date'] ?? null, 'invoice_date');
        $dueDate = $this->parseDate($firstRow['due_date'] ?? null, 'due_date', $invoiceDate->copy());
        $templateName = trim((string) ($firstRow['template_name'] ?? '')) ?: 'invoice1';

        $invoice = Invoice::create([
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'invoice_number' => $invoiceNumber,
            'reference_number' => trim((string) ($firstRow['reference_number'] ?? '')) ?: $invoiceNumber,
            'document_type' => Invoice::DOCUMENT_TYPE_INVOICE,
            'template_name' => $templateName,
            'status' => $status,
            'paid_status' => $paidStatus,
            'tax_per_item' => 'NO',
            'tax_included' => $this->parseBoolean($firstRow['tax_included'] ?? null),
            'discount_per_item' => 'NO',
            'fiscal_payment_method_id' => $paymentMethod?->id,
            'company_id' => $companyId,
            'sub_total' => $subTotal,
            'total' => $total,
            'discount_type' => $discountType,
            'discount_val' => $discountValue,
            'discount' => $discountAmount,
            'tax' => $taxTotal,
            'due_amount' => $dueAmount,
            'notes' => trim((string) ($firstRow['notes'] ?? '')),
            'customer_id' => $customer->id,
            'exchange_rate' => $exchangeRate,
            'base_discount_val' => (int) round($discountValue * $exchangeRate),
            'base_sub_total' => (int) round($subTotal * $exchangeRate),
            'base_total' => (int) round($total * $exchangeRate),
            'base_tax' => (int) round($taxTotal * $exchangeRate),
            'base_due_amount' => (int) round($dueAmount * $exchangeRate),
            'currency_id' => $currencyId,
            'creator_id' => $user->id,
            'sent' => in_array($status, [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED, Invoice::STATUS_COMPLETED], true),
            'viewed' => in_array($status, [Invoice::STATUS_VIEWED, Invoice::STATUS_COMPLETED], true),
        ]);

        $serial = (new SerialNumberFormatter)
            ->setModel($invoice)
            ->setCompany($companyId)
            ->setCustomer($customer->id)
            ->setNextNumbers();

        $invoice->sequence_number = $serial->nextSequenceNumber;
        $invoice->customer_sequence_number = $serial->nextCustomerSequenceNumber;
        $invoice->unique_hash = Hashids::connection(Invoice::class)->encode($invoice->id);

        [$fiscalStatus, $fiscalInvoiceNumber, $fiscalizedAt, $verificationUrl] = $this->resolveFiscalFields($firstRow);

        if ($fiscalStatus) {
            $invoice->fiscal_status = $fiscalStatus;
            $invoice->fiscal_invoice_number = $fiscalInvoiceNumber;
            $invoice->fiscalized_at = $fiscalizedAt;
            $invoice->fiscal_verification_url = $verificationUrl;
        }

        $invoice->save();

        foreach ($items as $itemPayload) {
            $invoice->items()->create($itemPayload);
        }

        if ($fiscalStatus) {
            OfsFiscalization::updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'company_id' => $companyId,
                ],
                [
                    'request_id' => 'legacy-import-'.$invoice->id,
                    'status' => $fiscalStatus,
                    'fiscal_invoice_number' => $fiscalInvoiceNumber,
                    'sdc_date_time' => $fiscalizedAt,
                    'verification_url' => $verificationUrl,
                    'total_amount' => $total / 100,
                    'error_message' => $fiscalStatus === OfsFiscalization::STATUS_FAILED
                        ? trim((string) ($firstRow['fiscal_error'] ?? 'Imported as failed fiscal invoice'))
                        : null,
                ]
            );
        }
    }

    private function buildInvoiceItemPayload(array $row, int $companyId, float $exchangeRate): array
    {
        $name = trim((string) ($row['item_name'] ?? ''));

        if ($name === '') {
            throw new \RuntimeException('Item name is required for every imported invoice row.');
        }

        $quantity = $this->parseDecimal($row['quantity'] ?? null, null);

        if ($quantity === null) {
            throw new \RuntimeException('Item quantity is required for every imported invoice row.');
        }

        $price = $this->resolveMoneyValue($row['unit_price'] ?? null, null);

        if ($price === null) {
            throw new \RuntimeException('Unit price is required for every imported invoice row.');
        }

        $total = $this->resolveMoneyValue(
            $row['line_total'] ?? null,
            (int) round($price * $quantity)
        );
        $catalogItem = $this->resolveCatalogItem($row, $companyId);
        $ofsGtin = trim((string) ($row['ofs_gtin'] ?? ''));

        if ($ofsGtin === '' && $catalogItem?->ofs_gtin) {
            $ofsGtin = (string) $catalogItem->ofs_gtin;
        }

        return [
            'item_id' => $catalogItem?->id,
            'name' => $name,
            'description' => trim((string) ($row['item_description'] ?? '')),
            'discount_type' => 'fixed',
            'price' => $price,
            'quantity' => $quantity,
            'discount' => 0,
            'discount_val' => 0,
            'tax' => 0,
            'total' => $total,
            'company_id' => $companyId,
            'exchange_rate' => $exchangeRate,
            'base_price' => (int) round($price * $exchangeRate),
            'base_discount_val' => 0,
            'base_tax' => 0,
            'base_total' => (int) round($total * $exchangeRate),
            'unit_name' => trim((string) ($row['unit_name'] ?? '')) ?: ($catalogItem?->unit?->name ?? 'kom'),
            'ofs_gtin' => $ofsGtin !== '' ? $ofsGtin : null,
        ];
    }

    private function resolveCustomer(array $row, User $user, int $companyId, int $currencyId): Customer
    {
        $customerName = trim((string) ($row['customer_name'] ?? ''));

        if ($customerName === '') {
            throw new \RuntimeException('Customer name is required.');
        }

        $customerEmail = $this->stringOrNull($row['customer_email'] ?? null);
        $customerTaxId = $this->stringOrNull($row['customer_tax_id'] ?? null);
        $customerPhone = $this->stringOrNull($row['customer_phone'] ?? null);

        $customer = Customer::where('company_id', $companyId)
            ->when($customerTaxId, function ($query, $customerTaxId) {
                $query->where('tax_id', $customerTaxId);
            })
            ->when(! $customerTaxId && $customerEmail, function ($query) use ($customerEmail) {
                $query->where('email', $customerEmail);
            })
            ->when(! $customerTaxId && ! $customerEmail, function ($query) use ($customerName) {
                $query->where('name', $customerName);
            })
            ->first();

        if ($customer) {
            return $customer;
        }

        return Customer::create([
            'name' => $customerName,
            'contact_name' => $customerName,
            'company_name' => $customerName,
            'email' => $customerEmail,
            'phone' => $customerPhone,
            'tax_id' => $customerTaxId,
            'currency_id' => $currencyId,
            'company_id' => $companyId,
            'creator_id' => $user->id,
            'enable_portal' => false,
        ]);
    }

    private function resolveCatalogItem(array $row, int $companyId): ?Item
    {
        $ofsGtin = trim((string) ($row['ofs_gtin'] ?? ''));

        if ($ofsGtin !== '') {
            $item = Item::where('company_id', $companyId)->where('ofs_gtin', $ofsGtin)->first();

            if ($item) {
                return $item;
            }
        }

        $itemName = trim((string) ($row['item_name'] ?? ''));

        return $itemName !== ''
            ? Item::where('company_id', $companyId)->where('name', $itemName)->first()
            : null;
    }

    private function resolveCurrencyId(?string $currencyCode, int $companyId): int
    {
        $currencyCode = strtoupper(trim((string) $currencyCode));

        if ($currencyCode !== '') {
            $currencyId = Currency::where('code', $currencyCode)->value('id');

            if ($currencyId) {
                return (int) $currencyId;
            }
        }

        return (int) CompanySetting::getSetting('currency', $companyId);
    }

    private function resolvePaymentMethod(array $row, int $companyId): ?PaymentMethod
    {
        $paymentMethodName = $this->stringOrNull($row['payment_method'] ?? null);
        $fiscalPaymentType = $this->stringOrNull($row['fiscal_payment_type'] ?? null);

        if ($paymentMethodName) {
            $paymentMethod = PaymentMethod::where('company_id', $companyId)
                ->whereRaw('LOWER(name) = ?', [strtolower($paymentMethodName)])
                ->first();

            if ($paymentMethod) {
                return $paymentMethod;
            }
        }

        if ($fiscalPaymentType) {
            $paymentMethod = PaymentMethod::where('company_id', $companyId)
                ->whereRaw('LOWER(ofs_payment_type) = ?', [strtolower($fiscalPaymentType)])
                ->first();

            if ($paymentMethod) {
                return $paymentMethod;
            }
        }

        return PaymentMethod::where('company_id', $companyId)
            ->whereNotNull('ofs_payment_type')
            ->first();
    }

    private function resolveFiscalFields(array $row): array
    {
        $fiscalInvoiceNumber = $this->stringOrNull($row['fiscal_receipt_number'] ?? null);
        $verificationUrl = $this->stringOrNull($row['fiscal_verification_url'] ?? null);
        $fiscalizedAt = $this->parseDate($row['fiscalized_at'] ?? null, 'fiscalized_at', null, true);
        $isOfsInvoice = $this->parseBoolean($row['is_ofs'] ?? null)
            || $fiscalInvoiceNumber !== null
            || $verificationUrl !== null
            || $fiscalizedAt !== null
            || trim((string) ($row['fiscal_status'] ?? '')) !== '';

        if (! $isOfsInvoice) {
            return [null, null, null, null];
        }

        $fiscalStatus = $this->normalizeFiscalStatus($row['fiscal_status'] ?? null, $fiscalInvoiceNumber);

        return [$fiscalStatus, $fiscalInvoiceNumber, $fiscalizedAt, $verificationUrl];
    }

    private function normalizeFiscalStatus(?string $value, ?string $fiscalInvoiceNumber): string
    {
        $value = strtoupper(trim((string) $value));

        return match ($value) {
            OfsFiscalization::STATUS_PENDING,
            OfsFiscalization::STATUS_FAILED,
            OfsFiscalization::STATUS_FISCALIZED => $value,
            default => $fiscalInvoiceNumber ? OfsFiscalization::STATUS_FISCALIZED : OfsFiscalization::STATUS_PENDING,
        };
    }

    private function normalizeInvoiceStatus(?string $value, string $paidStatus, int $dueAmount, int $total): string
    {
        $value = strtoupper(trim((string) $value));

        if (in_array($value, [
            Invoice::STATUS_DRAFT,
            Invoice::STATUS_SENT,
            Invoice::STATUS_VIEWED,
            Invoice::STATUS_COMPLETED,
        ], true)) {
            return $value;
        }

        if ($paidStatus === Invoice::STATUS_PAID || $dueAmount === 0) {
            return Invoice::STATUS_COMPLETED;
        }

        if ($dueAmount > 0 && $dueAmount < $total) {
            return Invoice::STATUS_VIEWED;
        }

        return Invoice::STATUS_SENT;
    }

    private function normalizePaidStatus(?string $value, int $dueAmount, int $total): string
    {
        $value = strtoupper(trim((string) $value));

        if (in_array($value, [
            Invoice::STATUS_UNPAID,
            Invoice::STATUS_PARTIALLY_PAID,
            Invoice::STATUS_PAID,
        ], true)) {
            return $value;
        }

        if ($dueAmount <= 0) {
            return Invoice::STATUS_PAID;
        }

        if ($dueAmount < $total) {
            return Invoice::STATUS_PARTIALLY_PAID;
        }

        return Invoice::STATUS_UNPAID;
    }

    private function normalizeDiscountType(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return $value === 'percentage' ? 'percentage' : 'fixed';
    }

    private function resolveMoneyValue($value, ?int $default): ?int
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return $default;
        }

        return $this->parseMoneyToMinorUnits($value);
    }

    private function parseMoneyToMinorUnits(string $value): int
    {
        $value = trim(str_replace(['KM', 'BAM', 'km', 'bam', ' '], '', $value));

        if ($value === '') {
            throw new \RuntimeException('Invalid money value.');
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        if (! is_numeric($value)) {
            throw new \RuntimeException('Invalid money value.');
        }

        return (int) round(((float) $value) * 100);
    }

    private function parseDecimal($value, ?float $default): ?float
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return $default;
        }

        $normalized = str_replace([' '], '', $value);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new \RuntimeException('Invalid decimal value.');
        }

        return (float) $normalized;
    }

    private function parseDate($value, string $field, ?Carbon $default = null, bool $allowTime = false): ?Carbon
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return $default;
        }

        try {
            $date = Carbon::parse($value);

            return $allowTime ? $date : $date->startOfDay();
        } catch (\Throwable $exception) {
            throw new \RuntimeException("Invalid {$field} value.");
        }
    }

    private function parseBoolean($value): bool
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'da', 'ofs', 'fiscalized'], true);
    }

    private function stringOrNull($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function groupRowsByInvoiceNumber(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $rowNumber => $row) {
            $invoiceNumber = trim((string) ($row['invoice_number'] ?? ''));

            if ($invoiceNumber === '') {
                throw new \RuntimeException("Invoice number is required on row {$rowNumber}.");
            }

            if (! isset($grouped[$invoiceNumber])) {
                $grouped[$invoiceNumber] = [
                    'row' => $rowNumber,
                    'rows' => [],
                ];
            }

            $grouped[$invoiceNumber]['rows'][$rowNumber] = $row;
        }

        return $grouped;
    }

    private function readImportCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to read CSV file.');
        }

        $firstLine = fgets($handle);
        $delimiter = $this->detectCsvDelimiter((string) $firstLine);
        rewind($handle);

        $headers = [];
        $rows = [];
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvRow($data)) {
                continue;
            }

            if ($headers === []) {
                $headers = $this->normalizeImportHeaders($data);
                continue;
            }

            $data = array_slice(array_pad($data, count($headers), ''), 0, count($headers));
            $rows[$rowNumber] = array_combine($headers, $data);
        }

        fclose($handle);

        return [$rows, $headers];
    }

    private function validateImportHeaders(array $headers): void
    {
        $requiredHeaders = [
            'invoice_number',
            'invoice_date',
            'customer_name',
            'item_name',
            'quantity',
            'unit_price',
        ];

        foreach ($requiredHeaders as $header) {
            if (! in_array($header, $headers, true)) {
                throw new \RuntimeException(
                    'CSV must contain the following columns: '.implode(', ', $requiredHeaders).'.'
                );
            }
        }
    }

    private function normalizeImportHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $key = Str::of((string) $header)
                ->trim()
                ->lower()
                ->replace([' ', '-', '.'], '_')
                ->toString();

            return match ($key) {
                'invoice_no', 'number', 'broj_fakture', 'broj' => 'invoice_number',
                'date', 'datum' => 'invoice_date',
                'due', 'due_on', 'rok_placanja', 'due_date' => 'due_date',
                'customer', 'kupac', 'customer_full_name' => 'customer_name',
                'customer_mail', 'email' => 'customer_email',
                'tax_id', 'pib', 'customer_pib' => 'customer_tax_id',
                'phone', 'telefon' => 'customer_phone',
                'currency', 'valuta' => 'currency_code',
                'exchange', 'exchange_rate_value' => 'exchange_rate',
                'payment', 'payment_mode', 'payment_name' => 'payment_method',
                'ofs_payment', 'ofs_payment_mode' => 'fiscal_payment_type',
                'product', 'item', 'stavka', 'naziv_stavke' => 'item_name',
                'opis', 'description' => 'item_description',
                'unit', 'jedinica' => 'unit_name',
                'price', 'cijena', 'unit_price_value' => 'unit_price',
                'amount', 'iznos_stavke', 'line_amount' => 'line_total',
                'subtotal', 'subtotal_amount', 'osnovica' => 'sub_total',
                'tax', 'pdv', 'tax_amount' => 'tax_total',
                'discount', 'popust' => 'discount_value',
                'fiscal_receipt_no', 'fiscal_number', 'ofs_receipt_number' => 'fiscal_receipt_number',
                'verification_url', 'ofs_verification_url' => 'fiscal_verification_url',
                'fiscal_date', 'ofs_date_time' => 'fiscalized_at',
                'ofs', 'fiscal', 'is_fiscal' => 'is_ofs',
                'gtin', 'barcode', 'ofs_gtin_number', 'ofs_grin' => 'ofs_gtin',
                default => $key,
            };
        }, $headers);
    }

    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    private function isEmptyCsvRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }
}
