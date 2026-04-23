<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Tax extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'percent' => 'float',
            'fixed_amount' => 'integer',
        ];
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function estimateItem(): BelongsTo
    {
        return $this->belongsTo(EstimateItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function scopeWhereCompany($query, $company_id)
    {
        $query->where('company_id', $company_id);
    }

    public function scopeTaxAttributes($query)
    {
        $query->select(
            DB::raw('sum(base_amount) as total_tax_amount, tax_type_id')
        )->groupBy('tax_type_id');
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        $query->whereHas('invoice', function ($query) use ($start, $end) {
            $query->where('paid_status', Invoice::STATUS_PAID)
                ->whereBetween(
                    'invoice_date',
                    [$start->format('Y-m-d'), $end->format('Y-m-d')]
                );
        })
            ->orWhereHas('invoiceItem.invoice', function ($query) use ($start, $end) {
                $query->where('paid_status', Invoice::STATUS_PAID)
                    ->whereBetween(
                        'invoice_date',
                        [$start->format('Y-m-d'), $end->format('Y-m-d')]
                    );
            });
    }

    public function scopeWhereInvoicesFilters($query, array $filters)
    {
        $filters = collect($filters);
        $invoiceScope = $filters->get('invoice_scope', Invoice::ACCESS_SCOPE_ALL);
        $start = $filters->get('from_date')
            ? Carbon::createFromFormat('Y-m-d', $filters->get('from_date'))
            : null;
        $end = $filters->get('to_date')
            ? Carbon::createFromFormat('Y-m-d', $filters->get('to_date'))
            : null;

        if ($start && $end || $invoiceScope !== Invoice::ACCESS_SCOPE_ALL) {
            return $query->where(function ($taxQuery) use ($start, $end, $invoiceScope) {
                $taxQuery->whereHas('invoice', function ($invoiceQuery) use ($start, $end, $invoiceScope) {
                    $invoiceQuery->where('paid_status', Invoice::STATUS_PAID)
                        ->when($start && $end, function ($query) use ($start, $end) {
                            $query->whereBetween(
                                'invoice_date',
                                [$start->format('Y-m-d'), $end->format('Y-m-d')]
                            );
                        })
                        ->applyInvoiceAccessScope($invoiceScope);
                })->orWhereHas('invoiceItem.invoice', function ($invoiceQuery) use ($start, $end, $invoiceScope) {
                    $invoiceQuery->where('paid_status', Invoice::STATUS_PAID)
                        ->when($start && $end, function ($query) use ($start, $end) {
                            $query->whereBetween(
                                'invoice_date',
                                [$start->format('Y-m-d'), $end->format('Y-m-d')]
                            );
                        })
                        ->applyInvoiceAccessScope($invoiceScope);
                });
            });
        }

        return $query;
    }
}
