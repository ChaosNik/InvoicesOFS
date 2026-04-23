<?php

use App\Mail\SendInvoiceMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OfsFiscalization;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\User;
use App\Services\Ofs\OfsFiscalizationService;
use App\Services\Ofs\OfsValidationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs($user, ['*']);
});

function invoicePayloadForOfsFeatureTest(array $overrides = []): array
{
    return array_replace_recursive(
        Invoice::factory()->raw([
            'taxes' => [Tax::factory()->raw()],
            'items' => [InvoiceItem::factory()->raw()],
        ]),
        $overrides
    );
}

function assignInvoiceRoleForOfsTest(User $user, int $companyId, string $roleName): void
{
    $user->update(['role' => $roleName]);
    $user->companies()->sync([$companyId]);

    BouncerFacade::scope()->to($companyId);
    BouncerFacade::sync($user)->roles([$roleName]);
}

test('creating an invoice in fake mode finalizes it with OFS metadata', function () {
    Queue::fake();

    $payload = invoicePayloadForOfsFeatureTest();

    $response = postJson('api/v1/invoices', $payload);

    $response->assertOk()
        ->assertJsonPath('data.fiscal_status', OfsFiscalization::STATUS_FISCALIZED);

    expect($response->json('data.fiscal_invoice_number'))->toStartWith('FAKE-OFS-');

    $this->assertDatabaseHas('ofs_fiscalizations', [
        'invoice_id' => $response->json('data.id'),
        'status' => OfsFiscalization::STATUS_FISCALIZED,
    ]);
});

test('mid level users can create non OFS invoices when fiscalization is turned off', function () {
    Queue::fake();

    $companyId = User::find(1)->companies()->first()->id;
    $midLevelUser = User::factory()->create();

    assignInvoiceRoleForOfsTest($midLevelUser, $companyId, Company::ROLE_MID_LEVEL_USER);

    Sanctum::actingAs($midLevelUser, ['*']);

    $payload = invoicePayloadForOfsFeatureTest([
        'use_ofs' => false,
        'fiscal_payment_method_id' => null,
        'items' => [
            InvoiceItem::factory()->raw([
                'ofs_gtin' => '',
            ]),
        ],
    ]);

    $response = postJson('api/v1/invoices', $payload);

    $response->assertOk()
        ->assertJsonPath('data.fiscal_status', null)
        ->assertJsonPath('data.fiscal_invoice_number', null)
        ->assertJsonPath('data.use_ofs', false);

    $this->assertDatabaseHas('invoices', [
        'id' => $response->json('data.id'),
        'fiscal_status' => null,
        'fiscal_payment_method_id' => null,
    ]);

    $this->assertDatabaseMissing('ofs_fiscalizations', [
        'invoice_id' => $response->json('data.id'),
    ]);
});

test('creating a credit note fiscalizes it as an OFS refund', function () {
    Queue::fake();

    $originalResponse = postJson('api/v1/invoices', invoicePayloadForOfsFeatureTest());

    $originalResponse->assertOk()
        ->assertJsonPath('data.fiscal_status', OfsFiscalization::STATUS_FISCALIZED);

    $payload = invoicePayloadForOfsFeatureTest([
        'document_type' => Invoice::DOCUMENT_TYPE_CREDIT_NOTE,
        'original_invoice_id' => $originalResponse->json('data.id'),
        'customer_id' => $originalResponse->json('data.customer_id'),
        'total' => $originalResponse->json('data.total'),
        'sub_total' => $originalResponse->json('data.sub_total'),
        'tax' => $originalResponse->json('data.tax'),
    ]);

    $response = postJson('api/v1/invoices', $payload);

    $response->assertOk()
        ->assertJsonPath('data.document_type', Invoice::DOCUMENT_TYPE_CREDIT_NOTE)
        ->assertJsonPath('data.original_invoice_id', $originalResponse->json('data.id'))
        ->assertJsonPath('data.referent_document_number', $originalResponse->json('data.fiscal_invoice_number'))
        ->assertJsonPath('data.fiscal_status', OfsFiscalization::STATUS_FISCALIZED);

    expect($response->json('data.fiscal_invoice_number'))->toStartWith('FAKE-OFS-REFUND-');

    $this->assertDatabaseHas('ofs_fiscalizations', [
        'invoice_id' => $response->json('data.id'),
        'status' => OfsFiscalization::STATUS_FISCALIZED,
    ]);

    expect(
        OfsFiscalization::where('invoice_id', $response->json('data.id'))->first()->request_payload['invoiceRequest']
    )->toMatchArray([
        'transactionType' => 'Refund',
        'referentDocumentNumber' => $originalResponse->json('data.fiscal_invoice_number'),
    ]);
});

test('HTTP OFS errors leave a failed fiscal attempt and a useful validation error', function () {
    Queue::fake();
    config([
        'ofs.driver' => 'http',
        'ofs.base_url' => 'http://ofs.test',
        'ofs.api_key' => 'secret-key',
    ]);

    Http::fake([
        'http://ofs.test/api/invoices' => Http::response([
            'message' => 'Device rejected invoice',
        ], 500),
    ]);

    $payload = invoicePayloadForOfsFeatureTest();

    $response = postJson('api/v1/invoices', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ofs');

    $invoice = Invoice::where('invoice_number', $payload['invoice_number'])->first();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'fiscal_status' => OfsFiscalization::STATUS_FAILED,
    ]);

    $this->assertDatabaseHas('ofs_fiscalizations', [
        'invoice_id' => $invoice->id,
        'status' => OfsFiscalization::STATUS_FAILED,
        'error_message' => 'Device rejected invoice',
    ]);

    Http::assertSent(fn (Request $request) => $request->url() === 'http://ofs.test/api/invoices'
        && $request->hasHeader('Authorization', 'Bearer secret-key'));
});

test('local OFS validation failure does not reserve the invoice number', function () {
    Queue::fake();

    $taxType = TaxType::factory()->create([
        'ofs_label' => null,
    ]);

    $payload = invoicePayloadForOfsFeatureTest([
        'taxes' => [
            Tax::factory()->raw([
                'tax_type_id' => $taxType->id,
                'name' => $taxType->name,
                'percent' => $taxType->percent,
            ]),
        ],
    ]);

    postJson('api/v1/invoices', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('ofs');

    $this->assertDatabaseMissing('invoices', [
        'invoice_number' => $payload['invoice_number'],
    ]);

    $taxType->update(['ofs_label' => 'A']);

    postJson('api/v1/invoices', $payload)
        ->assertOk()
        ->assertJsonPath('data.invoice_number', $payload['invoice_number'])
        ->assertJsonPath('data.fiscal_status', OfsFiscalization::STATUS_FISCALIZED);
});

test('fiscalized invoices cannot be edited', function () {
    Queue::fake();

    $invoice = Invoice::factory()->create([
        'fiscal_status' => OfsFiscalization::STATUS_FISCALIZED,
        'fiscal_invoice_number' => 'FAKE-OFS-LOCKED',
    ]);

    $payload = invoicePayloadForOfsFeatureTest();

    putJson('api/v1/invoices/'.$invoice->id, $payload)
        ->assertForbidden();
});

test('fiscalized invoices cannot be fiscalized again', function () {
    $invoice = Invoice::factory()->create([
        'fiscal_status' => OfsFiscalization::STATUS_FISCALIZED,
        'fiscal_invoice_number' => 'FAKE-OFS-LOCKED',
    ]);

    expect(fn () => app(OfsFiscalizationService::class)->fiscalizeInvoice($invoice, User::find(1)))
        ->toThrow(OfsValidationException::class, 'already been fiscalized');
});

test('non-fiscalized invoices cannot be emailed', function () {
    Mail::fake();

    $invoice = Invoice::factory()->create();

    postJson('api/v1/invoices/'.$invoice->id.'/send', [
        'from' => 'john@example.com',
        'to' => 'doe@example.com',
        'subject' => 'email subject',
        'body' => 'email body',
    ])->assertStatus(422)
        ->assertJson([
            'error' => 'invoice_not_fiscalized',
        ]);

    Mail::assertNotSent(SendInvoiceMail::class);
});
