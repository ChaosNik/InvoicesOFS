<?php

namespace App\Http\Controllers\V1\Admin\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $company = Company::find($request->header('company'));
        $user = $request->user();

        $this->authorize('view dashboard', $company);

        $invoiceScope = $this->resolveInvoiceScope($request, $user, $company->id);
        $range = $this->resolveChartRange($request);

        $chartData = $range['type'] === 'custom'
            ? $this->buildCustomRangeChartData($range['start'], $range['end'], $invoiceScope)
            : $this->buildFiscalYearChartData($range['start'], $invoiceScope);

        $total_sales = $this->sumInvoiceTotals($range['start'], $range['end'], $invoiceScope);
        $total_receipts = $this->sumReceiptTotals($range['start'], $range['end'], $invoiceScope);
        $total_expenses = $this->sumExpenseTotals($range['start'], $range['end']);

        $total_net_income = (int) $total_receipts - (int) $total_expenses;

        $total_customer_count = Customer::whereCompany()->count();
        $total_invoice_count = Invoice::whereCompany()
            ->applyInvoiceAccessScope($invoiceScope)
            ->count();
        $total_estimate_count = Estimate::whereCompany()->count();
        $total_amount_due = Invoice::whereCompany()
            ->applyInvoiceAccessScope($invoiceScope)
            ->sum('base_due_amount');

        $recent_due_invoices = Invoice::with('customer')
            ->whereCompany()
            ->applyInvoiceAccessScope($invoiceScope)
            ->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();
        $recent_estimates = Estimate::with('customer')->whereCompany()->take(5)->latest()->get();

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $total_customer_count,
            'total_invoice_count' => $total_invoice_count,
            'total_estimate_count' => $total_estimate_count,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chartData,

            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
            'active_invoice_scope' => $invoiceScope,
            'active_range_type' => $range['type'],
        ]);
    }

    private function resolveInvoiceScope(Request $request, $user, int $companyId): string
    {
        $requestedScope = $request->input('invoice_scope', $user->getDashboardInvoiceScope($companyId));

        if (! $user->canViewNonOfsInvoices($companyId)) {
            return Invoice::ACCESS_SCOPE_OFS_ONLY;
        }

        return in_array($requestedScope, [Invoice::ACCESS_SCOPE_ALL, Invoice::ACCESS_SCOPE_OFS_ONLY], true)
            ? $requestedScope
            : Invoice::ACCESS_SCOPE_ALL;
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

    private function buildFiscalYearChartData(Carbon $startDate, string $invoiceScope): array
    {
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netIncomeTotals = [];
        $months = [];
        $start = $startDate->copy()->startOfMonth();
        $end = $startDate->copy()->endOfMonth();

        for ($monthCounter = 0; $monthCounter < 12; $monthCounter++) {
            $invoiceTotals[] = $this->sumInvoiceTotals($start, $end, $invoiceScope);
            $expenseTotals[] = $this->sumExpenseTotals($start, $end);
            $receiptTotals[] = $this->sumReceiptTotals($start, $end, $invoiceScope);
            $netIncomeTotals[] = $receiptTotals[$monthCounter] - $expenseTotals[$monthCounter];
            $months[] = $start->translatedFormat('M');

            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        return [
            'months' => $months,
            'invoice_totals' => $invoiceTotals,
            'expense_totals' => $expenseTotals,
            'receipt_totals' => $receiptTotals,
            'net_income_totals' => $netIncomeTotals,
        ];
    }

    private function buildCustomRangeChartData(Carbon $startDate, Carbon $endDate, string $invoiceScope): array
    {
        $invoiceTotals = [];
        $expenseTotals = [];
        $receiptTotals = [];
        $netIncomeTotals = [];
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

            $invoiceTotals[] = $this->sumInvoiceTotals($bucketStart, $bucketEnd, $invoiceScope);
            $expenseTotals[] = $this->sumExpenseTotals($bucketStart, $bucketEnd);
            $receiptTotals[] = $this->sumReceiptTotals($bucketStart, $bucketEnd, $invoiceScope);
            $lastIndex = count($receiptTotals) - 1;
            $netIncomeTotals[] = $receiptTotals[$lastIndex] - $expenseTotals[$lastIndex];
            $months[] = $label;
        }

        return [
            'months' => $months,
            'invoice_totals' => $invoiceTotals,
            'expense_totals' => $expenseTotals,
            'receipt_totals' => $receiptTotals,
            'net_income_totals' => $netIncomeTotals,
        ];
    }

    private function sumInvoiceTotals(Carbon $startDate, Carbon $endDate, string $invoiceScope): int
    {
        return (int) Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->applyInvoiceAccessScope($invoiceScope)
            ->sum('base_total');
    }

    private function sumExpenseTotals(Carbon $startDate, Carbon $endDate): int
    {
        return (int) Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');
    }

    private function sumReceiptTotals(Carbon $startDate, Carbon $endDate, string $invoiceScope): int
    {
        return (int) Payment::whereBetween(
            'payment_date',
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        )
            ->whereCompany()
            ->applyInvoiceAccessScope($invoiceScope)
            ->sum('base_amount');
    }
}
