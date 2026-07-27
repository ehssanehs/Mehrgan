<?php

use App\Services\VlessParserService;

it('validates UUID correctly', function () {
    expect(VlessParserService::isValidUuid('6e5ab8e7-1a34-4d9d-9a2c-9f3e2b6d8c1a'))->toBeTrue();
    expect(VlessParserService::isValidUuid('invalid-uuid'))->toBeFalse();
    expect(VlessParserService::isValidUuid(''))->toBeFalse();
});

it('extracts UUID from VLESS URI', function () {
    $uri = 'vless://6e5ab8e7-1a34-4d9d-9a2c-9f3e2b6d8c1a@1.2.3.4:443?type=ws&security=tls#Test';
    $uuid = VlessParserService::extractUuidFromVless($uri);
    expect($uuid)->toBe('6e5ab8e7-1a34-4d9d-9a2c-9f3e2b6d8c1a');

    $invalid = 'vless://invalid@1.2.3.4:443';
    expect(VlessParserService::extractUuidFromVless($invalid))->toBeNull();

    $notVless = 'vmess://something';
    expect(VlessParserService::extractUuidFromVless($notVless))->toBeNull();
});

it('detects input type', function () {
    expect(VlessParserService::detectInputType('vless://uuid@host:port'))->toBe('vless');
    expect(VlessParserService::detectInputType('https://example.com/sub/abc'))->toBe('url');
    expect(VlessParserService::detectInputType('invalid input'))->toBe('invalid');
    expect(VlessParserService::detectInputType(''))->toBe('invalid');
});

it('parses subscription content with base64', function () {
    $vless1 = 'vless://11111111-1111-4111-8111-111111111111@1.1.1.1:443?type=tcp#Test1';
    $vless2 = 'vless://22222222-2222-4222-8222-222222222222@2.2.2.2:443?type=ws#Test2';
    $plain = $vless1 . "\n" . $vless2;

    $parsed = VlessParserService::parseSubscriptionContent($plain);
    expect($parsed)->toHaveCount(2);
    expect($parsed[0]['uuid'])->toBe('11111111-1111-4111-8111-111111111111');

    // Base64 encoded
    $encoded = base64_encode($plain);
    $parsed2 = VlessParserService::parseSubscriptionContent($encoded);
    expect($parsed2)->toHaveCount(2);
    expect($parsed2[0]['uuid'])->toBe('11111111-1111-4111-8111-111111111111');
});

it('uses only first UUID from subscription', function () {
    $vless1 = 'vless://aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa@1.1.1.1:443#First';
    $vless2 = 'vless://bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb@2.2.2.2:443#Second';
    $content = $vless1 . "\n" . $vless2;
    $parsed = VlessParserService::parseSubscriptionContent($content);
    expect($parsed[0]['uuid'])->toBe('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
});
