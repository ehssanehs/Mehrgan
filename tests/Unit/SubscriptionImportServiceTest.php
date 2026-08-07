<?php

use App\Services\SubscriptionImportService;
use Carbon\Carbon;

it('parses unixtime seconds into the correct expiry date', function () {
    $ts = Carbon::parse('2026-12-07 00:00:00')->timestamp;
    $result = SubscriptionImportService::parseExpiryValue($ts);
    expect($result)->not->toBeNull();
    expect($result->format('Y-m-d'))->toBe('2026-12-07');
});

it('parses milliseconds into the correct expiry date', function () {
    $ms = Carbon::parse('2026-12-07 00:00:00')->timestamp * 1000;
    $result = SubscriptionImportService::parseExpiryValue($ms);
    expect($result)->not->toBeNull();
    expect($result->format('Y-m-d'))->toBe('2026-12-07');
});

it('parses microseconds into the correct expiry date (fork fix)', function () {
    // A fork reporting expiry in microseconds must NOT jump thousands of years.
    $micro = Carbon::parse('2026-12-07 00:00:00')->timestamp * 1000000;
    $result = SubscriptionImportService::parseExpiryValue($micro);
    expect($result)->not->toBeNull();
    expect($result->format('Y-m-d'))->toBe('2026-12-07');
});

it('returns null for unlimited / invalid expiry values', function () {
    expect(SubscriptionImportService::parseExpiryValue(0))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue(-1))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue(null))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue(''))->toBeNull();
    expect(SubscriptionImportService::parseExpiryValue('not-a-number'))->toBeNull();
});
