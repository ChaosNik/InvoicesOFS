<?php

namespace App\Http\Controllers\V1\Admin\Item;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\DeleteItemsRequest;
use App\Http\Resources\ItemResource;
use App\Models\CompanySetting;
use App\Models\Item;
use App\Models\TaxType;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemsController extends Controller
{
    /**
     * Retrieve a list of existing Items.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $items = Item::whereCompany()
            ->leftJoin('units', 'units.id', '=', 'items.unit_id')
            ->applyFilters($request->all())
            ->select('items.*', 'units.name as unit_name')
            ->latest()
            ->paginateData($limit);

        return ItemResource::collection($items)
            ->additional(['meta' => [
                'tax_types' => TaxType::whereCompany()->latest()->get(),
                'item_total_count' => Item::whereCompany()->count(),
            ]]);
    }

    /**
     * Create Item.
     *
     * @param  App\Http\Requests\ItemsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Requests\ItemsRequest $request)
    {
        $this->authorize('create', Item::class);

        $item = Item::createItem($request);

        return new ItemResource($item);
    }

    public function import(Requests\ImportItemsRequest $request)
    {
        $this->authorize('create', Item::class);

        $companyId = (int) $request->header('company');
        $currencyId = CompanySetting::getSetting('currency', $companyId);
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            [$rows, $headers] = $this->readImportCsv($request->file('file')->getRealPath());
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $hasTaxesColumn = in_array('taxes', $headers, true);

        foreach ($rows as $rowNumber => $row) {
            try {
                $this->importItemRow($row, $rowNumber, $companyId, $currencyId, $hasTaxesColumn, $result);
            } catch (\Throwable $exception) {
                $result['skipped']++;
                $result['errors'][] = [
                    'row' => $rowNumber,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * get an existing Item.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Item $item)
    {
        $this->authorize('view', $item);

        return new ItemResource($item);
    }

    /**
     * Update an existing Item.
     *
     * @param  App\Http\Requests\ItemsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Requests\ItemsRequest $request, Item $item)
    {
        $this->authorize('update', $item);

        $item = $item->updateItem($request);

        return new ItemResource($item);
    }

    /**
     * Delete a list of existing Items.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(DeleteItemsRequest $request)
    {
        $this->authorize('delete multiple items');

        Item::destroy($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }

    private function importItemRow(array $row, int $rowNumber, int $companyId, $currencyId, bool $hasTaxesColumn, array &$result): void
    {
        $name = trim((string) ($row['name'] ?? ''));
        $price = trim((string) ($row['price'] ?? ''));
        $ofsGtin = trim((string) ($row['ofs_gtin'] ?? ''));

        if ($name === '') {
            throw new \RuntimeException('Item name is required.');
        }

        if ($price === '') {
            throw new \RuntimeException('Item price is required.');
        }

        if ($ofsGtin !== '' && (strlen($ofsGtin) < 8 || strlen($ofsGtin) > 14)) {
            throw new \RuntimeException('OFS GTIN must be 8-14 characters.');
        }

        $unit = $this->resolveImportUnit($row['unit'] ?? '', $companyId);
        $taxes = $hasTaxesColumn ? $this->resolveImportTaxes($row['taxes'] ?? '', $companyId, $this->parseMoneyToMinorUnits($price)) : null;

        DB::transaction(function () use ($row, $companyId, $currencyId, $name, $price, $ofsGtin, $unit, $taxes, &$result) {
            $item = $this->findImportItem($name, $ofsGtin, $companyId);
            $payload = [
                'name' => $name,
                'description' => trim((string) ($row['description'] ?? '')),
                'price' => $this->parseMoneyToMinorUnits($price),
                'ofs_gtin' => $ofsGtin !== '' ? $ofsGtin : null,
                'unit_id' => $unit?->id,
                'company_id' => $companyId,
                'currency_id' => $currencyId,
                'creator_id' => Auth::id(),
            ];

            if ($item) {
                $this->authorize('update', $item);
                $item->update($payload);
                $result['updated']++;
            } else {
                $item = Item::create($payload);
                $result['created']++;
            }

            if ($taxes !== null) {
                $item->taxes()->delete();
                $item->tax_per_item = count($taxes) > 0;
                $item->save();

                foreach ($taxes as $tax) {
                    $item->taxes()->create($tax);
                }
            }
        });
    }

    private function readImportCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException('Unable to read CSV file.');
        }

        $firstLine = fgets($handle);
        $delimiter = $this->detectCsvDelimiter((string) $firstLine);
        rewind($handle);

        $headers = [];
        $rows = [];
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->isEmptyCsvRow($data)) {
                continue;
            }

            if ($headers === []) {
                $headers = $this->normalizeImportHeaders($data);
                continue;
            }

            $data = array_slice(array_pad($data, count($headers), ''), 0, count($headers));
            $rows[$rowNumber] = array_combine(
                $headers,
                $data
            );
        }

        fclose($handle);

        if (! in_array('name', $headers, true) || ! in_array('price', $headers, true)) {
            throw new \RuntimeException('CSV must contain name and price columns.');
        }

        return [$rows, $headers];
    }

    private function normalizeImportHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $key = Str::of((string) $header)
                ->trim()
                ->lower()
                ->replace([' ', '-', '.'], '_')
                ->toString();

            return match ($key) {
                'item', 'item_name', 'product', 'product_name', 'naziv' => 'name',
                'cijena', 'cena', 'unit_price' => 'price',
                'jedinica', 'unit_name' => 'unit',
                'gtin', 'barcode', 'bar_code', 'ofs_grin', 'ofs_gtin_number' => 'ofs_gtin',
                'opis' => 'description',
                'tax', 'tax_names', 'tax_label', 'tax_labels', 'porez', 'porezi' => 'taxes',
                default => $key,
            };
        }, $headers);
    }

    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($delimiters);

        return (string) array_key_first($delimiters);
    }

    private function isEmptyCsvRow(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function resolveImportUnit(string $unitName, int $companyId): ?Unit
    {
        $unitName = trim($unitName) ?: 'kom';

        $unit = Unit::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($unitName)])
            ->first();

        return $unit ?: Unit::create([
            'company_id' => $companyId,
            'name' => $unitName,
        ]);
    }

    private function resolveImportTaxes(string $taxNames, int $companyId, int $price): array
    {
        $taxNames = collect(preg_split('/[;|]/', $taxNames) ?: [])
            ->map(fn ($taxName) => trim($taxName))
            ->filter();

        if ($taxNames->isEmpty()) {
            return [];
        }

        $taxTypes = TaxType::where('company_id', $companyId)->get();

        return $taxNames->map(function ($taxName) use ($taxTypes, $companyId, $price) {
            $taxType = $taxTypes->first(function ($taxType) use ($taxName) {
                return strcasecmp($taxType->name, $taxName) === 0
                    || strcasecmp((string) $taxType->ofs_label, $taxName) === 0;
            });

            if (! $taxType) {
                throw new \RuntimeException("Unknown tax type or OFS label: {$taxName}.");
            }

            return [
                'company_id' => $companyId,
                'tax_type_id' => $taxType->id,
                'name' => $taxType->name,
                'calculation_type' => $taxType->calculation_type,
                'percent' => $taxType->percent,
                'fixed_amount' => $taxType->fixed_amount,
                'amount' => $taxType->calculation_type === 'fixed'
                    ? (int) $taxType->fixed_amount
                    : (int) round(($price / 100) * (float) $taxType->percent),
                'collective_tax' => 0,
            ];
        })->values()->all();
    }

    private function findImportItem(string $name, string $ofsGtin, int $companyId): ?Item
    {
        if ($ofsGtin !== '') {
            $item = Item::where('company_id', $companyId)->where('ofs_gtin', $ofsGtin)->first();

            if ($item) {
                return $item;
            }
        }

        return Item::where('company_id', $companyId)->where('name', $name)->first();
    }

    private function parseMoneyToMinorUnits(string $value): int
    {
        $value = trim(str_replace(['KM', 'BAM', 'km', 'bam', ' '], '', $value));

        if ($value === '') {
            throw new \RuntimeException('Invalid item price.');
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $value = str_replace($thousandSeparator, '', $value);
            $value = str_replace($decimalSeparator, '.', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        if (! is_numeric($value)) {
            throw new \RuntimeException('Invalid item price.');
        }

        return (int) round(((float) $value) * 100);
    }
}
