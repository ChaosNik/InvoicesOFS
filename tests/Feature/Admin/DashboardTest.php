<?php

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

getJson('api/v1/dashboard')->assertOk();

getJson('api/v1/search?name=ab')->assertOk();

test('dashboard supports a custom date range for sales and expenses', function () {
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

    getJson('/api/v1/dashboard?range_type=custom&from_date=2024-01-10&to_date=2024-01-11')
        ->assertOk()
        ->assertJsonPath('total_sales', 1000)
        ->assertJsonPath('total_receipts', 500)
        ->assertJsonPath('total_expenses', 700)
        ->assertJsonPath('total_net_income', -200)
        ->assertJsonPath('active_range_type', 'custom')
        ->assertJsonCount(2, 'chart_data.months');
});
