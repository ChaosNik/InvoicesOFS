<?php

namespace App\Http\Controllers\V1\Admin\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CustomerStatsController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $range = $this->resolveChartRange($request);
        $chartData = $range['type'] === 'custom'
            ? $this->buildCustomRangeChartData($customer, $range['start'], $range['end'])
            : $this->buildFiscalYearChartData($customer, $range['start']);

        $salesTotal = $this->sumInvoiceTotals($customer, $range['start'], $range['end']);
        $totalReceipts = $this->sumReceiptTotals($customer, $range['start'], $range['end']);
        $totalExpenses = $this->sumExpenseTotals($customer, $range['start'], $range['end']);
        $netProfit = (int) $totalReceipts - (int) $totalExpenses;

        $chartData['netProfit'] = $netProfit;
        $chartData['salesTotal'] = $salesTotal;
        $chartData['totalReceipts'] = $totalReceipts;
        $chartData['totalExpenses'] = $totalExpenses;
        $chartData['rangeType'] = $range['type'];

        $customer = Customer::query()
            ->whereKey($customer->id)
            ->withFinancialTotals()
            ->firstOrFail();

        return (new CustomerResource($customer))
            ->additional(['meta' => [
                'chartData' => $chartData,
            ]]);
    }

    private function resolveChartRange(Request $request): array
    {
        $validated = $request->validate([
            'range_type' => 'nullable|in:this_year,previous_year,custom',
            'from_date' => 'nullable|date|required_if:range_type,custom',
            'to_date' => 'nullable|date|required_if:range_type,custom',
        ]);

        $rangeType = $validated['range_type'] ?? ($request->has('previous_year') ? 'previous_year' : 'this_year');

        if ($rangeType === 'custom') {
            $startDate = Carbon::createFromFormat('Y-m-d', $validated['from_date'])->startOfDay();
            $endDate = Carbon::createFromFormat('Y-m-d', $validated['to_date'])->endOfDay();

            if ($startDate->gt($endDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            return [
                'type' => 'custom',
                'start' => $startDate,
                'end' => $endDate,
            ];
        }

        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $startDate = $this->resolveFiscalYearStartDate($fiscalYear);

        if ($rangeType === 'previous_year') {
            $startDate->subYear()->startOfMonth();
        }

        return [
            'type' => $rangeType,
            'start' => $startDate,
            'end' => $startDate->copy()->addMonths(11)->endOfMonth(),
        ];
    }

    private function resolveFiscalYearStartDate(?string $fiscalYear): Carbon
    {
        $startDate = Carbon::now();
        $terms = explode('-', (string) $fiscalYear);
        $companyStartMonth = intval($terms[0] ?? 1);

        if ($companyStartMonth < 1 || $companyStartMonth > 12) {
            $companyStartMonth = 1;
        }

        if ($companyStartMonth <= $startDate->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
        }

        return $startDate;
    }

    private function buildFiscalYearChartData(Customer $customer, Carbon $startDate): array
    {
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netProfits = [];
        $months = [];
        $start = $startDate->copy()->startOfMonth();
        $end = $startDate->copy()->endOfMonth();

        for ($monthCounter = 0; $monthCounter < 12; $monthCounter++) {
            $invoiceTotals[] = $this->sumInvoiceTotals($customer, $start, $end);
            $expenseTotals[] = $this->sumExpenseTotals($customer, $start, $end);
            $receiptTotals[] = $this->sumReceiptTotals($customer, $start, $end);
            $netProfits[] = $receiptTotals[$monthCounter] - $expenseTotals[$monthCounter];
            $months[] = $start->translatedFormat('M');

            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        return [
            'months' => $months,
            'invoiceTotals' => $invoiceTotals,
            'expenseTotals' => $expenseTotals,
            'receiptTotals' => $receiptTotals,
            'netProfits' => $netProfits,
        ];
    }

    private function buildCustomRangeChartData(Customer $customer, Carbon $startDate, Carbon $endDate): array
    {
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netProfits = [];
        $months = [];
        $useDailyBuckets = $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) <= 31;
        $cursor = $useDailyBuckets
            ? $startDate->copy()->startOfDay()
            : $startDate->copy()->startOfMonth();

        while ($cursor->lte($endDate)) {
            if ($useDailyBuckets) {
                $bucketStart = $cursor->copy()->startOfDay();
                $bucketEnd = $cursor->copy()->endOfDay();
                $label = $bucketStart->translatedFormat('d.m.');
                $cursor->addDay()->startOfDay();
            } else {
                $bucketStart = $cursor->copy()->startOfMonth();
                $bucketEnd = $cursor->copy()->endOfMonth();

                if ($bucketStart->lt($startDate)) {
                    $bucketStart = $startDate->copy()->startOfDay();
                }

                if ($bucketEnd->gt($endDate)) {
                    $bucketEnd = $endDate->copy()->endOfDay();
                }

                $label = $cursor->translatedFormat('M y');
                $cursor->addMonth()->startOfMonth();
            }

            $invoiceTotals[] = $this->sumInvoiceTotals($customer, $bucketStart, $bucketEnd);
            $expenseTotals[] = $this->sumExpenseTotals($customer, $bucketStart, $bucketEnd);
            $receiptTotals[] = $this->sumReceiptTotals($customer, $bucketStart, $bucketEnd);
            $lastIndex = count($receiptTotals) - 1;
            $netProfits[] = $receiptTotals[$lastIndex] - $expenseTotals[$lastIndex];
            $months[] = $label;
        }

        return [
            'months' => $months,
            'invoiceTotals' => $invoiceTotals,
            'expenseTotals' => $expenseTotals,
            'receiptTotals' => $receiptTotals,
            'netProfits' => $netProfits,
        ];
    }

    private function sumInvoiceTotals(Customer $customer, Carbon $startDate, Carbon $endDate): int
    {
        return (int) Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereCustomer($customer->id)
            ->sum('base_total');
    }

    private function sumExpenseTotals(Customer $customer, Carbon $startDate, Carbon $endDate): int
    {
        return (int) Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereUser($customer->id)
            ->sum('base_amount');
    }

    private function sumReceiptTotals(Customer $customer, Carbon $startDate, Carbon $endDate): int
    {
        return (int) Payment::whereBetween(
            'payment_date',
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->whereCustomer($customer->id)
            ->sum('base_amount');
    }
}
