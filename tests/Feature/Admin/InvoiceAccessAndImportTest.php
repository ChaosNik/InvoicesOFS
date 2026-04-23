<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OfsFiscalization;
use App\Models\PaymentMethod;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $this->admin = User::find(1);
    $this->companyId = $this->admin->companies()->first()->id;

    $this->withHeaders([
        'company' => $this->companyId,
    ]);
});

function assignCompanyRoleToUser(User $user, int $companyId, string $roleName): void
{
    $user->update(['role' => $roleName]);
    $user->companies()->sync([$companyId]);

    BouncerFacade::scope()->to($companyId);
    BouncerFacade::sync($user)->roles([$roleName]);
}

test('low level OFS users see only OFS invoices and dashboard statistics stay on OFS scope', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->companyId,
    ]);

    $ofsInvoice = Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'fiscal_status' => OfsFiscalization::STATUS_FISCALIZED,
        'fiscal_invoice_number' => 'OFS-ONLY-1',
        'total' => 1000,
        'base_total' => 1000,
        'due_amount' => 1000,
        'base_due_amount' => 1000,
        'exchange_rate' => 1,
    ]);

    $nonOfsInvoice = Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'fiscal_status' => null,
        'fiscal_invoice_number' => null,
        'fiscalized_at' => null,
        'total' => 2000,
        'base_total' => 2000,
        'due_amount' => 2000,
        'base_due_amount' => 2000,
        'exchange_rate' => 1,
    ]);

    $lowLevelUser = User::factory()->create();
    assignCompanyRoleToUser($lowLevelUser, $this->companyId, Company::ROLE_LOW_LEVEL_OFS_USER);

    Sanctum::actingAs($lowLevelUser, ['*']);

    $invoiceResponse = getJson('/api/v1/invoices?limit=all');

    $invoiceResponse->assertOk();

    $invoiceIds = collect($invoiceResponse->json('data'))->pluck('id');

    expect($invoiceIds)
        ->toContain($ofsInvoice->id)
        ->not->toContain($nonOfsInvoice->id);

    getJson("/api/v1/invoices/{$nonOfsInvoice->id}")
        ->assertForbidden();

    getJson('/api/v1/dashboard?invoice_scope=all')
        ->assertOk()
        ->assertJsonPath('active_invoice_scope', Invoice::ACCESS_SCOPE_OFS_ONLY)
        ->assertJsonPath('total_invoice_count', 1);
});

test('mid level users can see all invoices and toggle dashboard scope', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->companyId,
    ]);

    $ofsInvoice = Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'fiscal_status' => OfsFiscalization::STATUS_FISCALIZED,
        'fiscal_invoice_number' => 'MID-OFS-1',
        'total' => 1000,
        'base_total' => 1000,
        'exchange_rate' => 1,
    ]);

    $nonOfsInvoice = Invoice::factory()->create([
        'company_id' => $this->companyId,
        'customer_id' => $customer->id,
        'fiscal_status' => null,
        'fiscal_invoice_number' => null,
        'fiscalized_at' => null,
        'total' => 1500,
        'base_total' => 1500,
        'exchange_rate' => 1,
    ]);

    $midLevelUser = User::factory()->create();
    assignCompanyRoleToUser($midLevelUser, $this->companyId, Company::ROLE_MID_LEVEL_USER);

    Sanctum::actingAs($midLevelUser, ['*']);

    $invoiceResponse = getJson('/api/v1/invoices?limit=all');

    $invoiceResponse->assertOk();

    $invoiceIds = collect($invoiceResponse->json('data'))->pluck('id');

    expect($invoiceIds)
        ->toContain($ofsInvoice->id)
        ->toContain($nonOfsInvoice->id);

    getJson('/api/v1/dashboard?invoice_scope=ofs_only')
        ->assertOk()
        ->assertJsonPath('active_invoice_scope', Invoice::ACCESS_SCOPE_OFS_ONLY)
        ->assertJsonPath('total_invoice_count', 1);

    getJson('/api/v1/dashboard?invoice_scope=all')
        ->assertOk()
        ->assertJsonPath('active_invoice_scope', Invoice::ACCESS_SCOPE_ALL)
        ->assertJsonPath('total_invoice_count', 2);
});

test('demo seed user is assigned the mid level role for the company', function () {
    $demoUser = User::where('email', 'demo@invoiceshelf.com')->first();

    expect($demoUser)->not->toBeNull();
    expect($demoUser->role)->toBe(Company::ROLE_MID_LEVEL_USER);
    expect($demoUser->getCompanyRole($this->companyId)?->name)->toBe(Company::ROLE_MID_LEVEL_USER);
});

test('low level OFS users can access non owner business modules while remaining OFS scoped', function () {
    $lowLevelUser = User::factory()->create();
    assignCompanyRoleToUser($lowLevelUser, $this->companyId, Company::ROLE_LOW_LEVEL_OFS_USER);

    Sanctum::actingAs($lowLevelUser, ['*']);

    getJson('/api/v1/expenses?limit=all')
        ->assertOk();

    getJson('/api/v1/payments?limit=all')
        ->assertOk();

    getJson('/api/v1/estimates?limit=all')
        ->assertOk();
});

test('legacy invoice csv import creates invoices, items, customers, and imported OFS metadata', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $csv = implode("\n", [
        'invoice_number,invoice_date,due_date,customer_name,customer_email,item_name,quantity,unit_price,line_total,sub_total,tax_total,total,due_amount,currency_code,is_ofs,fiscal_receipt_number,fiscalized_at,fiscal_verification_url',
        'OLD-0001,2026-01-15,2026-01-15,Legacy Kupac,legacy@example.com,Artikal A,2,10.00,20.00,30.00,0.00,30.00,0.00,BAM,yes,OFS-123,2026-01-15 10:15:00,https://verify.example/1',
        'OLD-0001,2026-01-15,2026-01-15,Legacy Kupac,legacy@example.com,Artikal B,1,10.00,10.00,30.00,0.00,30.00,0.00,BAM,yes,OFS-123,2026-01-15 10:15:00,https://verify.example/1',
    ]);

    $file = UploadedFile::fake()->createWithContent('legacy-invoices.csv', $csv);

    post('/api/v1/invoices/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.skipped', 0);

    $invoice = Invoice::where('company_id', $this->companyId)
        ->where('invoice_number', 'OLD-0001')
        ->first();

    expect($invoice)->not->toBeNull();

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'fiscal_status' => OfsFiscalization::STATUS_FISCALIZED,
        'fiscal_invoice_number' => 'OFS-123',
        'total' => 3000,
        'due_amount' => 0,
    ]);

    expect($invoice->items()->count())->toBe(2);

    $this->assertDatabaseHas('customers', [
        'company_id' => $this->companyId,
        'name' => 'Legacy Kupac',
        'email' => 'legacy@example.com',
    ]);

    $this->assertDatabaseHas('ofs_fiscalizations', [
        'invoice_id' => $invoice->id,
        'status' => OfsFiscalization::STATUS_FISCALIZED,
        'fiscal_invoice_number' => 'OFS-123',
    ]);
});

test('low level OFS users cannot import legacy invoices', function () {
    $lowLevelUser = User::factory()->create();
    assignCompanyRoleToUser($lowLevelUser, $this->companyId, Company::ROLE_LOW_LEVEL_OFS_USER);

    Sanctum::actingAs($lowLevelUser, ['*']);

    $file = UploadedFile::fake()->createWithContent('legacy-invoices.csv', implode("\n", [
        'invoice_number,invoice_date,customer_name,item_name,quantity,unit_price',
        'OLD-0002,2026-01-15,Legacy Kupac,Artikal A,1,10.00',
    ]));

    post('/api/v1/invoices/import', ['file' => $file], ['Accept' => 'application/json'])
        ->assertForbidden();
});

test('low level OFS users are always fiscalized even if use_ofs is disabled in the request', function () {
    $lowLevelUser = User::factory()->create();
    assignCompanyRoleToUser($lowLevelUser, $this->companyId, Company::ROLE_LOW_LEVEL_OFS_USER);

    Sanctum::actingAs($lowLevelUser, ['*']);

    $payload = Invoice::factory()->raw([
        'company_id' => $this->companyId,
        'customer_id' => Customer::factory()->create([
            'company_id' => $this->companyId,
        ])->id,
        'fiscal_payment_method_id' => PaymentMethod::factory()->create([
            'company_id' => $this->companyId,
            'ofs_payment_type' => 'Cash',
        ])->id,
        'use_ofs' => false,
        'taxes' => [Tax::factory()->raw([
            'company_id' => $this->companyId,
        ])],
        'items' => [InvoiceItem::factory()->raw([
            'company_id' => $this->companyId,
        ])],
    ]);

    post('/api/v1/invoices', $payload, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.fiscal_status', OfsFiscalization::STATUS_FISCALIZED);
});
