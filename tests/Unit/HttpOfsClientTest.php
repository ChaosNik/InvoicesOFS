<?php

use App\Services\Ofs\HttpOfsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('HTTP OFS client sends bearer auth, RequestId, and JSON payload', function () {
    Http::fake([
        'http://ofs.test/api/invoices' => Http::response([
            'invoiceNumber' => 'OFS-1',
            'sdcDateTime' => '2026-04-18T10:00:00+00:00',
            'verificationUrl' => 'https://ofs.test/verify/OFS-1',
            'totalAmount' => 10.0,
        ]),
    ]);

    $payload = [
        'invoiceRequest' => [
            'invoiceType' => 'Normal',
        ],
    ];

    $response = (new HttpOfsClient('http://ofs.test', 'secret-key', 5))
        ->fiscalize($payload, 'request-1');

    expect($response['invoiceNumber'])->toBe('OFS-1');

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'http://ofs.test/api/invoices'
        && $request->hasHeader('Authorization', 'Bearer secret-key')
        && $request->hasHeader('RequestId', 'request-1')
        && $request['invoiceRequest']['invoiceType'] === 'Normal');
});

test('HTTP OFS client recovers a timed out fiscalization by RequestId', function () {
    $requests = [];

    Http::fake(function (Request $request) use (&$requests) {
        $requests[] = [$request->method(), $request->url()];

        if ($request->method() === 'POST') {
            throw new ConnectionException('Timed out.');
        }

        return Http::response([
            'invoiceNumber' => 'OFS-RECOVERED',
            'sdcDateTime' => '2026-04-18T10:00:00+00:00',
            'verificationUrl' => 'https://ofs.test/verify/OFS-RECOVERED',
            'totalAmount' => 10.0,
        ]);
    });

    $response = (new HttpOfsClient('http://ofs.test', 'secret-key', 5))
        ->fiscalize(['invoiceRequest' => []], 'request-2');

    expect($response['invoiceNumber'])->toBe('OFS-RECOVERED')
        ->and($requests[0])->toBe(['POST', 'http://ofs.test/api/invoices'])
        ->and($requests[1])->toBe(['GET', 'http://ofs.test/api/invoices/request/request-2']);
});
