<?php

use App\Services\SubscriptionImportService;
use Carbon\Carbon;

it('parses expiry timestamps in seconds', function () {
    $ts = Carbon::parse('2026-08-07 10:00:00', 'UTC');

    $parsed = SubscriptionImportService::parseExpiryValue($ts->timestamp);

    expect($parsed)->toBeInstanceOf(Carbon::class);
    expect($parsed->format('Y-m-d H:i:s'))->toBe('2026-08-07 10:00:00');
});

it('parses expiry timestamps in milliseconds', function () {
    $ts = Carbon::parse('2026-08-07 10:00:00', 'UTC');

    $parsed = SubscriptionImportService::parseExpiryValue($ts->timestamp * 1000);

    expect($parsed)->toBeInstanceOf(Carbon::class);
    expect($parsed->format('Y-m-d H:i:s'))->toBe('2026-08-07 10:00:00');
});

it('parses expiry timestamps in microseconds', function () {
    $ts = Carbon::parse('2026-08-07 10:00:00', 'UTC');

    $parsed = SubscriptionImportService::parseExpiryValue($ts->timestamp * 1000000);

    expect($parsed)->toBeInstanceOf(Carbon::class);
    expect($parsed->format('Y-m-d H:i:s'))->toBe('2026-08-07 10:00:00');
});

it('parses expiry given as an ISO datetime string (PasarGuard style)', function () {
    $parsed = SubscriptionImportService::parseExpiryValue('2026-08-07T10:00:00');

    expect($parsed)->toBeInstanceOf(Carbon::class);
    expect($parsed->format('Y-m-d H:i:s'))->toBe('2026-08-07 10:00:00');
});

it('parses expiry given as a plain date string', function () {
    $parsed = SubscriptionImportService::parseExpiryValue('2026-12-07 08:30:00');

    expect($parsed)->toBeInstanceOf(Carbon::class);
    expect($parsed->format('Y-m-d H:i:s'))->toBe('2026-12-07 08:30:00');
});

it('parses numeric string timestamps like their integer form', function () {
    $ts = Carbon::parse('2026-08-07 10:00:00', 'UTC');

    $parsed = SubscriptionImportService::parseExpiryValue((string) $ts->timestamp);

    expect($parsed)->toBeInstanceOf(Carbon::class);
    expect($parsed->format('Y-m-d H:i:s'))->toBe('2026-08-07 10:00:00');
});

it('treats unlimited / empty expiry as null', function () {
    expect(SubscriptionImportService::parseExpiryValue(0))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue(null))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue(''))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue('not-a-number'))->toBeNull();
});

it('treats negative expiry (delayed start) as null', function () {
    expect(SubscriptionImportService::parseExpiryValue(-5184000000))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue(-2592000000))->toBeNull();
});
