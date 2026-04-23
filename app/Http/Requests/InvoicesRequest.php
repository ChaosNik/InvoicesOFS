<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OfsFiscalization;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.s
     */
    public function rules(): array
    {
        $shouldFiscalize = $this->shouldFiscalize();

        $rules = [
            'invoice_date' => [
                'required',
            ],
            'due_date' => [
                'nullable',
            ],
            'customer_id' => [
                'required',
            ],
            'invoice_number' => [
                'required',
                Rule::unique('invoices')->where('company_id', $this->header('company')),
            ],
            'document_type' => [
                'nullable',
                Rule::in([Invoice::DOCUMENT_TYPE_INVOICE, Invoice::DOCUMENT_TYPE_CREDIT_NOTE]),
            ],
            'use_ofs' => [
                'nullable',
                'boolean',
            ],
            'original_invoice_id' => [
                Rule::requiredIf(fn () => $this->input('document_type') === Invoice::DOCUMENT_TYPE_CREDIT_NOTE),
                'nullable',
                Rule::exists('invoices', 'id')
                    ->where('company_id', $this->header('company')),
            ],
            'credit_note_reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'discount' => [
                'numeric',
                'required',
            ],
            'discount_val' => [
                'integer',
                'required',
            ],
            'sub_total' => [
                'numeric',
                'required',
            ],
            'total' => [
                'numeric',
                'max:999999999999',
                'required',
            ],
            'tax' => [
                'required',
            ],
            'template_name' => [
                'required',
            ],
            'fiscal_payment_method_id' => $shouldFiscalize
                ? [
                    'required',
                    Rule::exists('payment_methods', 'id')
                        ->where('company_id', $this->header('company')),
                ]
                : [
                    'nullable',
                    Rule::exists('payment_methods', 'id')
                        ->where('company_id', $this->header('company')),
                ],
            'items' => [
                'required',
                'array',
            ],
            'items.*' => [
                'required',
                'max:255',
            ],
            'items.*.description' => [
                'nullable',
            ],
            'items.*.name' => [
                'required',
            ],
            'items.*.quantity' => [
                'numeric',
                'required',
            ],
            'items.*.price' => [
                'numeric',
                'required',
            ],
            'items.*.ofs_gtin' => $shouldFiscalize
                ? [
                    'required',
                    'string',
                    'min:8',
                    'max:14',
                ]
                : [
                    'nullable',
                    'string',
                    'max:14',
                ],
        ];

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($customer && $companyCurrency) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        if ($this->isMethod('PUT')) {
            $rules['invoice_number'] = [
                'required',
                Rule::unique('invoices')
                    ->ignore($this->route('invoice')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        if ($shouldFiscalize) {
            $paymentMethod = PaymentMethod::where('id', $this->fiscal_payment_method_id)
                ->where('company_id', $this->header('company'))
                ->first();

            if (! $paymentMethod?->ofs_payment_type) {
                $rules['fiscal_payment_method_id'][] = function ($attribute, $value, $fail) {
                    $fail('The selected payment mode must have an OFS payment type.');
                };
            }
        }

        return $rules;
    }

    public function shouldFiscalize(): bool
    {
        if ($this->input('document_type') === Invoice::DOCUMENT_TYPE_CREDIT_NOTE) {
            return true;
        }

        $companyId = (int) $this->header('company');

        if ($this->user() && $this->user()->mustUseOfsInvoices($companyId)) {
            return true;
        }

        if ($this->has('use_ofs')) {
            return $this->boolean('use_ofs');
        }

        $invoice = $this->route('invoice');

        if ($this->isMethod('PUT') && $invoice instanceof Invoice) {
            return $invoice->shouldUseOfs();
        }

        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->input('document_type') !== Invoice::DOCUMENT_TYPE_CREDIT_NOTE) {
                return;
            }

            $originalInvoice = Invoice::whereCompanyId($this->header('company'))
                ->find($this->original_invoice_id);

            if (! $originalInvoice) {
                return;
            }

            if ($originalInvoice->isCreditNote()) {
                $validator->errors()->add('original_invoice_id', 'A credit note cannot be created from another credit note.');
            }

            if ($originalInvoice->fiscal_status !== OfsFiscalization::STATUS_FISCALIZED) {
                $validator->errors()->add('original_invoice_id', 'A credit note can only be created from a fiscalized invoice.');
            }

            if (! $originalInvoice->fiscal_invoice_number || ! $originalInvoice->fiscalized_at) {
                $validator->errors()->add('original_invoice_id', 'The original invoice is missing OFS reference data.');
            }

            if ((int) $this->customer_id !== (int) $originalInvoice->customer_id) {
                $validator->errors()->add('customer_id', 'A credit note must use the same customer as the original invoice.');
            }

            $alreadyRefunded = (int) $originalInvoice->creditNotes()
                ->where('fiscal_status', OfsFiscalization::STATUS_FISCALIZED)
                ->sum('total');

            $remaining = max(0, (int) $originalInvoice->total - $alreadyRefunded);

            if ((int) round($this->total) > $remaining) {
                $validator->errors()->add('total', 'The credit note amount cannot exceed the remaining amount on the original invoice.');
            }
        });
    }

    public function getInvoicePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;
        $documentType = $this->input('document_type', Invoice::DOCUMENT_TYPE_INVOICE);
        $isCreditNote = $documentType === Invoice::DOCUMENT_TYPE_CREDIT_NOTE;
        $originalInvoice = $isCreditNote
            ? Invoice::whereCompanyId($this->header('company'))->find($this->original_invoice_id)
            : null;
        $dueAmount = $isCreditNote ? 0 : $this->total;

        return collect($this->except('items', 'taxes', 'use_ofs'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $isCreditNote ? Invoice::STATUS_COMPLETED : ($this->has('invoiceSend') ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT),
                'paid_status' => $isCreditNote ? Invoice::STATUS_PAID : Invoice::STATUS_UNPAID,
                'document_type' => $documentType,
                'original_invoice_id' => $originalInvoice?->id,
                'referent_document_number' => $originalInvoice?->fiscal_invoice_number,
                'referent_document_dt' => $originalInvoice?->fiscalized_at,
                'credit_note_reason' => $this->credit_note_reason,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO ',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'due_amount' => $dueAmount,
                'sent' => (bool) $this->sent ?? false,
                'viewed' => (bool) $this->viewed ?? false,
                'exchange_rate' => $exchange_rate,
                'base_total' => $this->total * $exchange_rate,
                'base_discount_val' => $this->discount_val * $exchange_rate,
                'base_sub_total' => $this->sub_total * $exchange_rate,
                'base_tax' => $this->tax * $exchange_rate,
                'base_due_amount' => $dueAmount * $exchange_rate,
                'currency_id' => $currency,
                'fiscal_payment_method_id' => $this->shouldFiscalize() ? $this->fiscal_payment_method_id : null,
            ])
            ->toArray();
    }
}
