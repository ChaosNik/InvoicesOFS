<?php

use App\Http\Controllers\V1\Admin\Customer\CustomersController;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get customers', function () {
    $response = getJson('api/v1/customers?page=1');

    $response->assertOk();
});

test('customer stats', function () {
    $customer = Customer::factory()->create();

    $invoice = Invoice::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $response = getJson("api/v1/customers/{$customer->id}/stats");

    $response->assertStatus(200);
});

test('customer stats support a custom date range', function () {
    $companyId = User::find(1)->companies()->first()->id;

    $customer = Customer::factory()->create([
        'company_id' => $companyId,
    ]);

    $paymentMethod = PaymentMethod::factory()->create([
        'company_id' => $companyId,
    ]);

    $expenseCategory = ExpenseCategory::factory()->create([
        'company_id' => $companyId,
    ]);

    Invoice::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'invoice_date' => '2024-01-10',
        'base_total' => 1000,
        'total' => 1000,
        'sub_total' => 1000,
        'base_sub_total' => 1000,
        'due_amount' => 1000,
        'base_due_amount' => 1000,
        'tax' => 0,
        'base_tax' => 0,
        'discount' => 0,
        'base_discount_val' => 0,
        'exchange_rate' => 1,
        'fiscal_payment_method_id' => $paymentMethod->id,
        'recurring_invoice_id' => null,
    ]);

    Invoice::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'invoice_date' => '2024-02-01',
        'base_total' => 9999,
        'total' => 9999,
        'sub_total' => 9999,
        'base_sub_total' => 9999,
        'due_amount' => 9999,
        'base_due_amount' => 9999,
        'tax' => 0,
        'base_tax' => 0,
        'discount' => 0,
        'base_discount_val' => 0,
        'exchange_rate' => 1,
        'fiscal_payment_method_id' => $paymentMethod->id,
        'recurring_invoice_id' => null,
    ]);

    Payment::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'payment_method_id' => $paymentMethod->id,
        'payment_date' => '2024-01-11',
        'base_amount' => 500,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'payment_method_id' => $paymentMethod->id,
        'payment_date' => '2024-02-01',
        'base_amount' => 8888,
        'amount' => 8888,
    ]);

    Expense::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'expense_category_id' => $expenseCategory->id,
        'expense_date' => '2024-01-10',
        'base_amount' => 700,
        'amount' => 700,
    ]);

    Expense::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'expense_category_id' => $expenseCategory->id,
        'expense_date' => '2024-02-01',
        'base_amount' => 7777,
        'amount' => 7777,
    ]);

    getJson("api/v1/customers/{$customer->id}/stats?range_type=custom&from_date=2024-01-10&to_date=2024-01-11")
        ->assertOk()
        ->assertJsonPath('meta.chartData.salesTotal', 1000)
        ->assertJsonPath('meta.chartData.totalReceipts', 500)
        ->assertJsonPath('meta.chartData.totalExpenses', 700)
        ->assertJsonPath('meta.chartData.netProfit', -200)
        ->assertJsonPath('meta.chartData.rangeType', 'custom')
        ->assertJsonCount(2, 'meta.chartData.months');
});

test('customer due amount is calculated as sales minus receipts', function () {
    $companyId = User::find(1)->companies()->first()->id;

    $customer = Customer::factory()->create([
        'company_id' => $companyId,
    ]);

    $paymentMethod = PaymentMethod::factory()->create([
        'company_id' => $companyId,
    ]);

    Invoice::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'invoice_date' => '2024-03-01',
        'base_total' => 1000,
        'total' => 1000,
        'sub_total' => 1000,
        'base_sub_total' => 1000,
        'due_amount' => 1000,
        'base_due_amount' => 1000,
        'tax' => 0,
        'base_tax' => 0,
        'discount' => 0,
        'base_discount_val' => 0,
        'exchange_rate' => 1,
        'fiscal_payment_method_id' => $paymentMethod->id,
        'recurring_invoice_id' => null,
    ]);

    Payment::factory()->create([
        'company_id' => $companyId,
        'customer_id' => $customer->id,
        'payment_method_id' => $paymentMethod->id,
        'payment_date' => '2024-03-02',
        'base_amount' => 300,
        'amount' => 300,
    ]);

    getJson('api/v1/customers?limit=all')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $customer->id,
            'due_amount' => 700,
            'base_due_amount' => 700,
        ]);

    getJson("api/v1/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonPath('data.due_amount', 700)
        ->assertJsonPath('data.base_due_amount', 700);

    getJson("api/v1/customers/{$customer->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.due_amount', 700)
        ->assertJsonPath('data.base_due_amount', 700);
});

test('create customer', function () {
    $customer = Customer::factory()->raw([
        'shipping' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
        'billing' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
    ]);

    postJson('api/v1/customers', $customer)
        ->assertOk();

    $this->assertDatabaseHas('customers', [
        'name' => $customer['name'],
        'email' => $customer['email'],
    ]);
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        CustomersController::class,
        'store',
        CustomerRequest::class
    );
});

test('get customer', function () {
    $customer = Customer::factory()->create();

    $response = getJson("api/v1/customers/{$customer->id}");

    $this->assertDatabaseHas('customers', [
        'id' => $customer->id,
        'name' => $customer['name'],
        'email' => $customer['email'],
    ]);

    $response->assertOk();
});

test('update customer', function () {
    $customer = Customer::factory()->create();

    $customer1 = Customer::factory()->raw([
        'shipping' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
        'billing' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
    ]);

    $response = putJson('api/v1/customers/'.$customer->id, $customer1);

    $customer1 = collect($customer1)
        ->only([
            'email',
        ])
        ->merge([
            'creator_id' => Auth::id(),
        ])
        ->toArray();

    $response->assertOk();

    $this->assertDatabaseHas('customers', $customer1);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        CustomersController::class,
        'update',
        CustomerRequest::class
    );
});

test('search customers', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'email' => '.com',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/customers?'.$queryString);

    $response->assertOk();
});

test('delete multiple customer', function () {
    $customers = Customer::factory()->count(4)->create();

    $ids = $customers->pluck('id');

    $data = [
        'ids' => $ids,
    ];

    $response = postJson('api/v1/customers/delete', $data);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
        ]);
});
