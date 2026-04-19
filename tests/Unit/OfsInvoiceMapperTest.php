<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\User;
use App\Services\Ofs\OfsInvoiceMapper;
use App\Services\Ofs\OfsValidationException;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

function createInvoiceForOfsMapperTest(
    array $invoiceAttributes = [],
    array $itemAttributes = [],
    array $taxTypeAttributes = [],
    array $customerAttributes = [],
    array $paymentMethodAttributes = []
): Invoice {
    $user = User::find(1);
    $companyId = $user->companies()->first()->id;

    $paymentMethod = PaymentMethod::factory()->create(array_merge([
        'company_id' => $companyId,
        'ofs_payment_type' => 'Card',
    ], $paymentMethodAttributes));

    $customer = Customer::factory()->create(array_merge([
        'company_id' => $companyId,
        'tax_id' => '1234567890123',
    ], $customerAttributes));

    $invoice = Invoice::factory()->create(array_merge([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'creator_id' => $user->id,
        'fiscal_payment_method_id' => $paymentMethod->id,
        'tax_per_item' => 'NO',
        'sub_total' => 10000,
        'total' => 12345,
        'tax' => 2345,
        'discount' => 0,
        'discount_val' => 0,
    ], $invoiceAttributes));

    InvoiceItem::factory()->create(array_merge([
        'invoice_id' => $invoice->id,
        'company_id' => $companyId,
        'ofs_gtin' => '1234567890123',
        'price' => 5000,
        'quantity' => 2,
        'total' => 10000,
        'tax' => 2345,
        'discount' => 0,
        'discount_val' => 0,
    ], $itemAttributes));

    $taxType = TaxType::factory()->create(array_merge([
        'company_id' => $companyId,
        'name' => 'VAT',
        'ofs_label' => 'F',
        'percent' => 17,
    ], $taxTypeAttributes));

    Tax::factory()->create([
        'invoice_id' => $invoice->id,
        'company_id' => $companyId,
        'tax_type_id' => $taxType->id,
        'name' => $taxType->name,
        'percent' => $taxType->percent,
        'amount' => 2345,
    ]);

    return $invoice->fresh();
}

test('maps InvoiceShelf invoice data into an OFS invoice request', function () {
    $payload = (new OfsInvoiceMapper)->map(
        createInvoiceForOfsMapperTest(),
        User::find(1)
    );

    expect($payload['print'])->toBeFalse()
        ->and($payload['invoiceRequest']['invoiceType'])->toBe('Normal')
        ->and($payload['invoiceRequest']['transactionType'])->toBe('Sale')
        ->and($payload['invoiceRequest']['buyerId'])->toBe('1234567890123')
        ->and($payload['invoiceRequest']['cashier'])->toBe(User::find(1)->name)
        ->and($payload['invoiceRequest']['payment'][0])->toMatchArray([
            'amount' => 123.45,
            'paymentType' => 'Card',
        ])
        ->and($payload['invoiceRequest']['items'][0])->toMatchArray([
            'gtin' => '1234567890123',
            'labels' => ['F'],
            'totalAmount' => 100.0,
            'unitPrice' => 50.0,
            'quantity' => 2.0,
        ]);
});

test('maps credit notes into OFS refund requests with original reference', function () {
    $payload = (new OfsInvoiceMapper)->map(
        createInvoiceForOfsMapperTest([
            'document_type' => Invoice::DOCUMENT_TYPE_CREDIT_NOTE,
            'referent_document_number' => 'FAKE-OFS-ORIGINAL',
            'referent_document_dt' => now(),
        ]),
        User::find(1)
    );

    expect($payload['invoiceRequest']['invoiceType'])->toBe('Normal')
        ->and($payload['invoiceRequest']['transactionType'])->toBe('Refund')
        ->and($payload['invoiceRequest']['referentDocumentNumber'])->toBe('FAKE-OFS-ORIGINAL')
        ->and($payload['invoiceRequest']['referentDocumentDT'])->toBeString();
});

test('credit note refunds require original OFS reference data', function () {
    $invoice = createInvoiceForOfsMapperTest([
        'document_type' => Invoice::DOCUMENT_TYPE_CREDIT_NOTE,
        'referent_document_number' => null,
        'referent_document_dt' => null,
    ]);

    expect(fn () => (new OfsInvoiceMapper)->map($invoice, User::find(1)))
        ->toThrow(OfsValidationException::class, 'OFS refund requires');
});

test('requires an OFS GTIN for each invoice item', function () {
    $invoice = createInvoiceForOfsMapperTest(itemAttributes: ['ofs_gtin' => '123']);

    expect(fn () => (new OfsInvoiceMapper)->map($invoice, User::find(1)))
        ->toThrow(OfsValidationException::class, 'OFS GTIN');
});

test('requires an OFS tax label', function () {
    $invoice = createInvoiceForOfsMapperTest(taxTypeAttributes: ['ofs_label' => null]);

    expect(fn () => (new OfsInvoiceMapper)->map($invoice, User::find(1)))
        ->toThrow(OfsValidationException::class, 'OFS label');
});

test('requires a fiscal payment type', function () {
    $invoice = createInvoiceForOfsMapperTest(paymentMethodAttributes: ['ofs_payment_type' => null]);

    expect(fn () => (new OfsInvoiceMapper)->map($invoice, User::find(1)))
        ->toThrow(OfsValidationException::class, 'payment mode');
});

test('requires a cashier', function () {
    $invoice = createInvoiceForOfsMapperTest(invoiceAttributes: ['creator_id' => null]);

    expect(fn () => (new OfsInvoiceMapper)->map($invoice))
        ->toThrow(OfsValidationException::class, 'cashier');
});
