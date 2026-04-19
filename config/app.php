<?php

use Illuminate\Support\Facades\Facade;

return [
    'locale' => env('APP_LOCALE', 'sr'),

    'fallback_locale' => 'en',

    'faker_locale' => 'sr_RS',

    'aliases' => Facade::defaultAliases()->merge([
        'Flash' => Laracasts\Flash\Flash::class,
        'Menu' => Lavary\Menu\Facade::class,
        'Pusher' => Pusher\Pusher::class,
        'Redis' => Illuminate\Support\Facades\Redis::class,
    ])->toArray(),

];
