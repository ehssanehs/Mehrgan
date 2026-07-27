<?php

use App\Services\SubscriptionFetcherService;

it('rejects invalid URLs', function () {
    $result = SubscriptionFetcherService::fetch('not-a-url');
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Invalid URL');
});

it('rejects non-http schemes', function () {
    $result = SubscriptionFetcherService::fetch('ftp://example.com/file');
    expect($result['success'])->toBeFalse();
});

it('blocks SSRF private IPs', function () {
    $blocked1 = SubscriptionFetcherService::isBlockedHost('127.0.0.1');
    expect($blocked1['blocked'])->toBeTrue();

    $blocked2 = SubscriptionFetcherService::isBlockedHost('localhost');
    expect($blocked2['blocked'])->toBeTrue();

    $blocked3 = SubscriptionFetcherService::isBlockedHost('192.168.1.1');
    expect($blocked3['blocked'])->toBeTrue();

    $blocked4 = SubscriptionFetcherService::isBlockedHost('10.0.0.1');
    expect($blocked4['blocked'])->toBeTrue();

    $allowed = SubscriptionFetcherService::isBlockedHost('8.8.8.8');
    expect($allowed['blocked'])->toBeFalse();

    $allowed2 = SubscriptionFetcherService::isBlockedHost('example.com');
    expect($allowed2['blocked'])->toBeFalse();
});

it('blocks SSRF on fetch', function () {
    $result = SubscriptionFetcherService::fetch('http://127.0.0.1/sub');
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not allowed');
});
