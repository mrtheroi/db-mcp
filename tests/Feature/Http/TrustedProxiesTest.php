<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function bootWithTrustedProxies(?string $value): void
{
    if ($value === null) {
        putenv('TRUSTED_PROXIES');
    } else {
        putenv("TRUSTED_PROXIES={$value}");
    }

    test()->refreshApplication();

    Route::get('/_scheme', fn (Request $request) => $request->getScheme());
}

afterEach(function () {
    putenv('TRUSTED_PROXIES');
    TrustProxies::flushState();
});

it('honours forwarded headers from proxies listed in TRUSTED_PROXIES', function () {
    bootWithTrustedProxies('*');

    $this->get('/_scheme', ['X-Forwarded-Proto' => 'https'])->assertSee('https');
});

it('accepts a comma separated list of proxy addresses', function () {
    bootWithTrustedProxies('10.0.0.1, 127.0.0.1');

    $this->get('/_scheme', ['X-Forwarded-Proto' => 'https'])->assertSee('https');
});

it('ignores forwarded headers when TRUSTED_PROXIES is not set', function () {
    bootWithTrustedProxies(null);

    $this->get('/_scheme', ['X-Forwarded-Proto' => 'https'])->assertSee('http')->assertDontSee('https');
});
