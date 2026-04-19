<div class="compact-document">
    @php
        $customer = $invoice->customer;
        $isCreditNote = $invoice->document_type === \App\Models\Invoice::DOCUMENT_TYPE_CREDIT_NOTE;
        $showSwiftFields = $showSwiftFields ?? false;
        $swiftFields = $showSwiftFields ? [
            'pdf_bank_name' => \App\Models\CompanySetting::getSetting('swift_bank_name', $invoice->company_id),
            'pdf_bank_account_number' => \App\Models\CompanySetting::getSetting('swift_account_number', $invoice->company_id),
            'pdf_iban' => \App\Models\CompanySetting::getSetting('swift_iban', $invoice->company_id),
            'pdf_swift_bic' => \App\Models\CompanySetting::getSetting('swift_bic', $invoice->company_id),
        ] : [];
    @endphp

    <table class="compact-header-table">
        <tr>
            <td width="30%" class="compact-party-block compact-company-block">
                @if ($logo)
                    <img class="compact-logo" src="{{ \App\Space\ImageUtils::toBase64Src($logo) }}" alt="@lang('company_logo')">
                @endif

                @if ($company_address)
                    {!! $company_address !!}
                @else
                    {{ optional($invoice->company)->name }}
                @endif
            </td>

            <td width="40%" class="compact-meta-cell">
                <div class="compact-meta">
                    <table>
                        <tr>
                            <td class="compact-meta-label">@lang($isCreditNote ? 'pdf_credit_note_number' : 'pdf_invoice_number')</td>
                            <td class="compact-meta-value">{{ $invoice->invoice_number }}</td>
                        </tr>
                        @if ($invoice->fiscal_invoice_number)
                            <tr class="compact-ofs-row">
                                <td class="compact-meta-label">@lang('pdf_ofs_fiscal_receipt_number')</td>
                                <td class="compact-meta-value">{{ $invoice->fiscal_invoice_number }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="compact-meta-label">@lang($isCreditNote ? 'pdf_credit_note_date' : 'pdf_invoice_date')</td>
                            <td class="compact-meta-value">{{ $invoice->formattedInvoiceDate }}</td>
                        </tr>
                        @if ($isCreditNote && $invoice->referent_document_number)
                            <tr>
                                <td class="compact-meta-label">@lang('pdf_credit_note_for_invoice')</td>
                                <td class="compact-meta-value">{{ $invoice->referent_document_number }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td class="compact-meta-label">@lang('pdf_invoice_due_date')</td>
                            <td class="compact-meta-value">{{ $invoice->formattedDueDate }}</td>
                        </tr>
                    </table>
                </div>
            </td>

            <td width="30%" class="compact-party-block compact-customer-block">
                @if ($billing_address)
                    <div class="compact-party-title">@lang('pdf_bill_to')</div>
                    {!! $billing_address !!}
                @elseif ($customer)
                    <div class="compact-party-title">@lang('pdf_bill_to')</div>
                    @if ($customer->company_name)
                        <strong>{{ $customer->company_name }}</strong><br>
                    @endif
                    {{ $customer->name }}<br>
                    @if ($customer->contact_name && $customer->contact_name !== $customer->name)
                        {{ $customer->contact_name }}<br>
                    @endif
                    @if ($customer->tax_id)
                        @lang('pdf_tax_id'): {{ $customer->tax_id }}<br>
                    @endif
                    @if ($customer->phone)
                        {{ $customer->phone }}<br>
                    @endif
                    @if ($customer->email)
                        {{ $customer->email }}
                    @endif
                @endif

                @if ($shipping_address && $shipping_address !== '</br>')
                    <div class="compact-party-title" style="margin-top: 7px;">@lang('pdf_ship_to')</div>
                    {!! $shipping_address !!}
                @endif
            </td>
        </tr>
    </table>

    @if ($showSwiftFields)
        <table class="compact-bank-table">
            <tr>
                <th colspan="4">@lang('pdf_swift_payment_details')</th>
            </tr>
            <tr>
                <td class="compact-bank-label">@lang('pdf_bank_name')</td>
                <td class="compact-bank-value">{{ $swiftFields['pdf_bank_name'] ?: ' ' }}</td>
                <td class="compact-bank-label">@lang('pdf_bank_account_number')</td>
                <td class="compact-bank-value">{{ $swiftFields['pdf_bank_account_number'] ?: ' ' }}</td>
            </tr>
            <tr>
                <td class="compact-bank-label">@lang('pdf_iban')</td>
                <td class="compact-bank-value">{{ $swiftFields['pdf_iban'] ?: ' ' }}</td>
                <td class="compact-bank-label">@lang('pdf_swift_bic')</td>
                <td class="compact-bank-value">{{ $swiftFields['pdf_swift_bic'] ?: ' ' }}</td>
            </tr>
        </table>
    @endif

    @include('app.pdf.invoice.partials.table')

    <table class="compact-signatures">
        <tr>
            <td>
                @lang('pdf_goods_delivered')
                <div class="signature-line"></div>
            </td>
            <td>
                @lang('pdf_goods_received')
                <div class="signature-line"></div>
            </td>
        </tr>
    </table>

    @if ($notes)
        <div class="notes">
            <div class="notes-label">@lang('pdf_notes')</div>
            {!! $notes !!}
        </div>
    @endif
</div>
