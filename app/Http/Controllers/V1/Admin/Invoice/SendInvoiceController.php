<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendInvoiceRequest;
use App\Models\Invoice;
use App\Models\OfsFiscalization;

class SendInvoiceController extends Controller
{
    /**
     * Mail a specific invoice to the corresponding customer's email address.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        if ($invoice->fiscal_status !== OfsFiscalization::STATUS_FISCALIZED) {
            return respondJson('invoice_not_fiscalized', 'Invoice must be fiscalized by OFS before it can be sent.');
        }

        $invoice->send($request->all());

        return response()->json([
            'success' => true,
        ]);
    }
}
