<?php

use App\Models\Order;
use App\Models\User;
use App\Services\VlessParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('telegram import prompt sets bot state', function () {
    $user = User::factory()->create([
        'telegram_chat_id' => 123456789,
        'bot_state' => null,
    ]);

    // Simulate showing import prompt
    $user->update(['bot_state' => 'awaiting_import_subscription']);
    expect($user->bot_state)->toBe('awaiting_import_subscription');

    // Simulate cancel action
    $user->update(['bot_state' => null]);
    expect($user->bot_state)->toBeNull();
});

it('detects VLESS vs URL in telegram flow', function () {
    $vless = 'vless://aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa@1.1.1.1:443#Test';
    $url = 'https://example.com/sub/abc123';

    expect(VlessParserService::detectInputType($vless))->toBe('vless');
    expect(VlessParserService::detectInputType($url))->toBe('url');
    expect(VlessParserService::detectInputType('invalid'))->toBe('invalid');
});

it('escapes the imported subscription expiration date for Telegram MarkdownV2', function () {
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $order = new Order([
        'panel_username' => 'نام کاربری',
        'panel_client_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'config_details' => 'vless://aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa@example.com:443?type=ws#اشتراک-آزمایشی',
        'expires_at' => now()->setDate(2026, 8, 7),
    ]);

    $method = (new ReflectionClass($controller))->getMethod('buildImportSuccessMessage');
    $method->setAccessible(true);
    $message = $method->invoke($controller, $order);

    // A raw 2026-08-07 makes Telegram reject a MarkdownV2 message because
    // hyphens are reserved characters. The result must contain escaped hyphens.
    expect($message)->toContain('2026\\-08\\-07');
    expect($message)->not->toContain('2026-08-07');
    expect(mb_check_encoding($message, 'UTF-8'))->toBeTrue();
});

it('telegram bot main menu includes import button', function () {
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getReplyMainMenu');
    $method->setAccessible(true);

    $keyboard = $method->invoke($controller, 123456);
    $keyboardArray = $keyboard->toArray();

    // Check that the reply keyboard contains the Persian import button text.
    $keyboardJson = json_encode($keyboardArray);
    expect($keyboardJson)->toContain('ورود اشتراک قبلی به ربات');
});
