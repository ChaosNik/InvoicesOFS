@php
    $showOfsGtin = $invoice->items->contains(function ($item) {
        return ! empty($item->ofs_gtin);
    });

    $summaryColspan = 5 + $customFields->count();

    if ($showOfsGtin) {
        $summaryColspan++;
    }

    if ($invoice->discount_per_item === 'YES') {
        $summaryColspan++;
    }

    if ($invoice->tax_per_item === 'YES') {
        $summaryColspan++;
    }
@endphp

<table width="100%" class="items-table" cellspacing="0" border="0">
    <thead>
        <tr class="item-table-heading-row">
            <th width="5%" class="item-table-heading">@lang('pdf_item_no')</th>
            <th class="text-left item-table-heading">@lang('pdf_items_label')</th>
            @if ($showOfsGtin)
                <th width="13%" class="item-table-heading">@lang('pdf_ofs_gtin_label')</th>
            @endif
            @foreach($customFields as $field)
                <th width="11%" class="item-table-heading">{{ $field->label }}</th>
            @endforeach
            <th width="10%" class="item-table-heading">@lang('pdf_unit_label')</th>
            <th width="9%" class="item-table-heading">@lang('pdf_quantity_label')</th>
            <th width="12%" class="item-table-heading">@lang('pdf_price_label')</th>
            @if($invoice->discount_per_item === 'YES')
                <th width="10%" class="item-table-heading">@lang('pdf_discount_label')</th>
            @endif
            @if($invoice->tax_per_item === 'YES')
                <th width="10%" class="item-table-heading">@lang('pdf_tax_label')</th>
            @endif
            <th width="12%" class="item-table-heading">@lang('pdf_amount_label')</th>
        </tr>
    </thead>

    <tbody>
        @php
            $index = 1
        @endphp

        @foreach ($invoice->items as $item)
            @php
                $ofsTaxLabels = $item->taxes->map(function ($tax) {
                    return optional($tax->taxType)->ofs_label;
                })->filter()->unique()->implode(', ');
            @endphp
            <tr class="item-row">
                <td class="text-center item-cell">{{ $index }}</td>
                <td class="text-left item-cell">
                    <span>{{ $item->name }}</span>
                    @if ($item->description)
                        <span class="item-description">{!! nl2br(htmlspecialchars($item->description)) !!}</span>
                    @endif
                    @if ($ofsTaxLabels)
                        <span class="ofs-item-meta">@lang('pdf_ofs_tax_label'): {{ $ofsTaxLabels }}</span>
                    @endif
                </td>
                @if ($showOfsGtin)
                    <td class="text-center item-cell">{{ $item->ofs_gtin ?: '-' }}</td>
                @endif
                @foreach($customFields as $field)
                    <td class="text-center item-cell">
                        {{ $item->getCustomFieldValueBySlug($field->slug) }}
                    </td>
                @endforeach
                <td class="text-center item-cell">{{ $item->unit_name ?: '-' }}</td>
                <td class="text-right item-cell">{{ $item->quantity }}</td>
                <td class="text-right item-cell">{!! format_money_pdf($item->price, $invoice->customer->currency) !!}</td>

                @if($invoice->discount_per_item === 'YES')
                    <td class="text-right item-cell">
                        @if($item->discount_type === 'fixed')
                            {!! format_money_pdf($item->discount_val, $invoice->customer->currency) !!}
                        @endif
                        @if($item->discount_type === 'percentage')
                            {{ $item->discount }}%
                        @endif
                    </td>
                @endif

                @if($invoice->tax_per_item === 'YES')
                    <td class="text-right item-cell">{!! format_money_pdf($item->tax, $invoice->customer->currency) !!}</td>
                @endif

                <td class="text-right item-cell">{!! format_money_pdf($item->total, $invoice->customer->currency) !!}</td>
            </tr>
            @php
                $index += 1
            @endphp
        @endforeach

        <tr class="summary-row">
            <td colspan="{{ $summaryColspan }}" class="summary-label">@lang('pdf_subtotal')</td>
            <td class="summary-value">{!! format_money_pdf($invoice->sub_total, $invoice->customer->currency) !!}</td>
        </tr>

        @if($invoice->discount > 0 && $invoice->discount_per_item === 'NO')
            <tr class="summary-row">
                <td colspan="{{ $summaryColspan }}" class="summary-label">
                    @if($invoice->discount_type === 'percentage')
                        @lang('pdf_discount_label') ({{ $invoice->discount }}%)
                    @else
                        @lang('pdf_discount_label')
                    @endif
                </td>
                <td class="summary-value">{!! format_money_pdf($invoice->discount_val, $invoice->customer->currency) !!}</td>
            </tr>
        @endif

        @if ($invoice->tax_included)
            <tr class="summary-row">
                <td colspan="{{ $summaryColspan }}" class="summary-label">@lang('pdf_net_total')</td>
                <td class="summary-value">
                    {!! format_money_pdf($invoice->sub_total - $invoice->discount - $invoice->tax, $invoice->customer->currency) !!}
                </td>
            </tr>
        @endif

        @if ($invoice->tax_per_item === 'YES')
            @foreach ($taxes as $tax)
                <tr class="summary-row">
                    <td colspan="{{ $summaryColspan }}" class="summary-label">
                        @if($tax->calculation_type === 'fixed')
                            {{ $tax->name }} ({!! format_money_pdf($tax->fixed_amount, $invoice->customer->currency) !!})
                        @else
                            {{ $tax->name.' ('.$tax->percent.'%)' }}
                        @endif
                    </td>
                    <td class="summary-value">{!! format_money_pdf($tax->amount, $invoice->customer->currency) !!}</td>
                </tr>
            @endforeach
        @else
            @foreach ($invoice->taxes as $tax)
                <tr class="summary-row">
                    <td colspan="{{ $summaryColspan }}" class="summary-label">
                        @if($tax->calculation_type === 'fixed')
                            {{ $tax->name }} ({!! format_money_pdf($tax->fixed_amount, $invoice->customer->currency) !!})
                        @else
                            {{ $tax->name.' ('.$tax->percent.'%)' }}
                        @endif
                    </td>
                    <td class="summary-value">{!! format_money_pdf($tax->amount, $invoice->customer->currency) !!}</td>
                </tr>
            @endforeach
        @endif

        <tr class="total-row">
            <td colspan="{{ $summaryColspan }}" class="summary-label">@lang('pdf_total')</td>
            <td class="summary-value">{!! format_money_pdf($invoice->total, $invoice->customer->currency)!!}</td>
        </tr>

        @if($invoice->paid_status === App\Models\Invoice::STATUS_PARTIALLY_PAID || $invoice->paid_status === App\Models\Invoice::STATUS_PAID)
            <tr class="summary-row">
                <td colspan="{{ $summaryColspan }}" class="summary-label">@lang('pdf_amount_paid')</td>
                <td class="summary-value">{!! format_money_pdf($invoice->total - $invoice->due_amount, $invoice->customer->currency)!!}</td>
            </tr>
            <tr class="summary-row">
                <td colspan="{{ $summaryColspan }}" class="summary-label">@lang('pdf_amount_due')</td>
                <td class="summary-value">{!! format_money_pdf($invoice->due_amount, $invoice->customer->currency)!!}</td>
            </tr>
        @endif
    </tbody>
</table>
