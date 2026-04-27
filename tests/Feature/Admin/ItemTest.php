<?php

use App\Http\Controllers\V1\Admin\Item\ItemsController;
use App\Http\Requests\ItemsRequest;
use App\Models\Item;
use App\Models\Tax;
use App\Models\TaxType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

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

test('get items', function () {
    $response = getJson('api/v1/items?page=1');

    $response->assertOk();
});

test('create item', function () {
    $item = Item::factory()->raw([
        'item_code' => 'ART-001',
        'taxes' => [
            Tax::factory()->raw(),
            Tax::factory()->raw(),
        ],
    ]);

    $response = postJson('api/v1/items', $item);

    $this->assertDatabaseHas('items', [
        'name' => $item['name'],
        'item_code' => $item['item_code'],
        'description' => $item['description'],
        'price' => $item['price'],
        'company_id' => $item['company_id'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'item_id' => $response->getData()->data->id,
    ]);

    $response->assertOk();
});

test('store validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ItemsController::class,
        'store',
        ItemsRequest::class
    );
});

test('get item', function () {
    $item = Item::factory()->create();

    $response = getJson("api/v1/items/{$item->id}");

    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => $item['price'],
        'company_id' => $item['company_id'],
    ]);
});

test('update item', function () {
    $item = Item::factory()->create();

    $update_item = Item::factory()->raw([
        'item_code' => 'ART-002',
        'taxes' => [
            Tax::factory()->raw(),
        ],
    ]);

    $response = putJson('api/v1/items/'.$item->id, $update_item);

    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'name' => $update_item['name'],
        'item_code' => $update_item['item_code'],
        'description' => $update_item['description'],
        'price' => $update_item['price'],
        'company_id' => $update_item['company_id'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'item_id' => $item->id,
    ]);
});

test('update validates using a form request', function () {
    $this->assertActionUsesFormRequest(
        ItemsController::class,
        'update',
        ItemsRequest::class
    );
});

test('delete multiple items', function () {
    $items = Item::factory()->count(5)->create();

    $data = [
        'ids' => $items->pluck('id'),
    ];

    postJson('/api/v1/items/delete', $data)->assertOk();

    foreach ($items as $item) {
        $this->assertModelMissing($item);
    }
});

test('search items', function () {
    $filters = [
        'page' => 1,
        'limit' => 15,
        'search' => 'doe',
        'price' => 6,
        'unit' => 'kg',
    ];

    $queryString = http_build_query($filters, '', '&');

    $response = getJson('api/v1/items?'.$queryString);

    $response->assertOk();
});

test('search items by OFS GTIN', function () {
    $matchingItem = Item::factory()->create([
        'name' => 'Coffee Beans',
        'ofs_gtin' => '9876543210123',
    ]);
    $otherItem = Item::factory()->create([
        'name' => 'Tea Leaves',
        'ofs_gtin' => '11112222',
    ]);

    $response = getJson('api/v1/items?search=9876543210123&limit=all');

    $response->assertOk();

    $itemIds = collect($response->json('data'))->pluck('id');

    expect($itemIds)
        ->toContain($matchingItem->id)
        ->not->toContain($otherItem->id);
});

test('search items by item code', function () {
    $matchingItem = Item::factory()->create([
        'name' => 'Coffee Beans',
        'item_code' => 'SKU-COFFEE-1',
    ]);
    $otherItem = Item::factory()->create([
        'name' => 'Tea Leaves',
        'item_code' => 'SKU-TEA-1',
    ]);

    $response = getJson('api/v1/items?search=SKU-COFFEE-1&limit=all');

    $response->assertOk();

    $itemIds = collect($response->json('data'))->pluck('id');

    expect($itemIds)
        ->toContain($matchingItem->id)
        ->not->toContain($otherItem->id);
});

test('filter items by exact OFS GTIN', function () {
    $matchingItem = Item::factory()->create([
        'name' => 'Chocolate Bar',
        'ofs_gtin' => '3871234567890',
    ]);
    $otherItem = Item::factory()->create([
        'name' => 'Orange Juice',
        'ofs_gtin' => '3870000000000',
    ]);

    $response = getJson('api/v1/items?ofs_gtin=3871234567890&limit=all');

    $response->assertOk();

    $itemIds = collect($response->json('data'))->pluck('id');

    expect($itemIds)
        ->toContain($matchingItem->id)
        ->not->toContain($otherItem->id);
});

test('create item with fixed amount tax', function () {
    $item = Item::factory()->raw([
        'taxes' => [
            Tax::factory()->raw([
                'calculation_type' => 'fixed',
                'fixed_amount' => 5000,
            ]),
        ],
    ]);

    $response = postJson('api/v1/items', $item);

    $response->assertOk();

    $this->assertDatabaseHas('items', [
        'name' => $item['name'],
        'description' => $item['description'],
        'price' => $item['price'],
        'company_id' => $item['company_id'],
    ]);

    $this->assertDatabaseHas('taxes', [
        'item_id' => $response->getData()->data->id,
        'calculation_type' => 'fixed',
        'fixed_amount' => 5000,
    ]);
});

test('import items from csv creates items with unit and OFS tax label', function () {
    $companyId = User::find(1)->companies()->first()->id;

    $taxType = TaxType::factory()->create([
        'company_id' => $companyId,
        'name' => 'PDV 17',
        'percent' => 17,
        'ofs_label' => 'E',
    ]);

    $csv = implode("\n", [
        'item_code,name,price,unit,ofs_gtin,description,taxes',
        'A-001,Kafa,12.50,kom,3871234567890,Opis,E',
    ]);

    $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

    $response = post('/api/v1/items/import', ['file' => $file], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('data.created', 1)
        ->assertJsonPath('data.updated', 0)
        ->assertJsonPath('data.skipped', 0);

    $item = Item::where('ofs_gtin', '3871234567890')->first();

    expect($item)->not->toBeNull();

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'item_code' => 'A-001',
        'name' => 'Kafa',
        'price' => 1250,
        'company_id' => $companyId,
    ]);

    $this->assertDatabaseHas('units', [
        'id' => $item->unit_id,
        'name' => 'kom',
        'company_id' => $companyId,
    ]);

    $this->assertDatabaseHas('taxes', [
        'item_id' => $item->id,
        'tax_type_id' => $taxType->id,
        'amount' => 213,
    ]);
});

test('import items from csv updates existing items by OFS GTIN', function () {
    $companyId = User::find(1)->companies()->first()->id;

    $item = Item::factory()->create([
        'company_id' => $companyId,
        'name' => 'Stara cijena',
        'ofs_gtin' => '3870000000001',
        'price' => 1000,
    ]);

    $csv = implode("\n", [
        'name;price;unit;ofs_gtin',
        'Nova cijena;22,40;kom;3870000000001',
    ]);

    $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

    $response = post('/api/v1/items/import', ['file' => $file], ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJsonPath('data.created', 0)
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('data.skipped', 0);

    $item->refresh();

    expect($item->name)->toBe('Nova cijena');
    expect($item->price)->toBe(2240);

    $this->assertDatabaseHas('units', [
        'id' => $item->unit_id,
        'name' => 'kom',
        'company_id' => $companyId,
    ]);
});

test('import items from csv requires name and price columns', function () {
    $file = UploadedFile::fake()->createWithContent('items.csv', "title,amount\nKafa,12.50");

    $response = post('/api/v1/items/import', ['file' => $file], ['Accept' => 'application/json']);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});
