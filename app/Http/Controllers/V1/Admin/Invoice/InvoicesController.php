<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\DeleteInvoiceRequest;
use App\Http\Requests\ImportInvoicesRequest;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Services\LegacyInvoiceImportService;
use App\Services\Ofs\OfsException;
use App\Services\Ofs\OfsFiscalizationService;
use App\Services\Ofs\OfsValidationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $limit = $request->input('limit', 10);

        $invoices = Invoice::whereCompany()
            ->accessibleByUser($request->user(), (int) $request->header('company'))
            ->applyFilters($request->all())
            ->with('customer')
            ->latest()
            ->paginateData($limit);

        return InvoiceResource::collection($invoices)
            ->additional(['meta' => [
                'invoice_total_count' => Invoice::whereCompany()
                    ->accessibleByUser($request->user(), (int) $request->header('company'))
                    ->applyFilters($request->only(['invoice_scope']))
                    ->when($request->input('document_type'), function ($query, $documentType) {
                        $query->where('document_type', $documentType);
                    })
                    ->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Requests\InvoicesRequest $request, OfsFiscalizationService $fiscalizationService)
    {
        $this->authorize('create', Invoice::class);

        $invoice = Invoice::createInvoice($request);

        try {
            if ($request->shouldFiscalize()) {
                $fiscalizationService->fiscalizeInvoice($invoice, $request->user());
            }
        } catch (OfsValidationException $exception) {
            $invoice->delete();

            throw ValidationException::withMessages([
                'ofs' => [$exception->getMessage()],
            ]);
        } catch (OfsException $exception) {
            throw ValidationException::withMessages([
                'ofs' => [$exception->getMessage()],
            ]);
        }

        $invoice = Invoice::with([
            'items',
            'items.fields',
            'items.fields.customField',
            'customer',
            'taxes',
            'fiscalization',
        ])->find($invoice->id);

        if ($request->has('invoiceSend')) {
            $invoice->send($request->subject, $request->body);
        }

        GenerateInvoicePdfJob::dispatch($invoice);

        return new InvoiceResource($invoice);
    }

    public function import(ImportInvoicesRequest $request, LegacyInvoiceImportService $legacyInvoiceImportService)
    {
        $this->authorize('create', Invoice::class);

        if (! $request->user()->canImportLegacyInvoices((int) $request->header('company'))) {
            abort(403, 'Legacy invoice import is not available for OFS-only users.');
        }

        try {
            $result = $legacyInvoiceImportService->import(
                $request->file('file')->getRealPath(),
                $request->user(),
                (int) $request->header('company')
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'file' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Requests\InvoicesRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if ($invoice->fiscal_status === \App\Models\OfsFiscalization::STATUS_FISCALIZED) {
            return respondJson('invoice_fiscalized', 'Fiscalized invoices cannot be edited.');
        }

        $invoice = $invoice->updateInvoice($request);

        if (is_string($invoice)) {
            return respondJson($invoice, $invoice);
        }

        GenerateInvoicePdfJob::dispatch($invoice, true);

        return new InvoiceResource($invoice);
    }

    /**
     * delete the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(DeleteInvoiceRequest $request)
    {
        $this->authorize('delete multiple invoices');

        Invoice::deleteInvoices($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
