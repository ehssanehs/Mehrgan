<?php

use App\Models\Setting;
use App\Models\TelegramBotSetting;
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

it('telegram bot main menu includes import button', function () {
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getReplyMainMenu');
    $method->setAccessible(true);

    $keyboard = $method->invoke($controller, 123456);
    $keyboardArray = $keyboard->toArray();

    // Check that keyboard contains import button text
    $keyboardJson = json_encode($keyboardArray);
    expect($keyboardJson)->toContain('Import Existing Subscription');
});

it('normalizes the token used by the webhook handler', function () {
    $token = '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi';
    Setting::create([
        'key' => 'telegram_bot_token',
        'value' => " bot{$token} \n",
    ]);

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getConfiguredBotToken');
    $method->setAccessible(true);

    expect($method->invoke($controller))->toBe($token);
});

it('uses a fallback when an optional Telegram message is empty', function () {
    TelegramBotSetting::create([
        'key' => 'welcome_message',
        'value' => '   ',
    ]);

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getConfiguredBotMessage');
    $method->setAccessible(true);

    expect($method->invoke($controller, 'welcome_message', 'Welcome to the bot'))
        ->toBe('Welcome to the bot');
});

it('does not put an invalid local web app URL in the first reply keyboard', function () {
    config()->set('app.url', 'http://localhost:8000');

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);

    $urlMethod = $reflection->getMethod('getPublicWebAppUrl');
    $urlMethod->setAccessible(true);
    expect($urlMethod->invoke($controller))->toBeNull();

    $menuMethod = $reflection->getMethod('getReplyMainMenu');
    $menuMethod->setAccessible(true);
    $keyboardJson = json_encode($menuMethod->invoke($controller, 123456)->toArray());

    expect($keyboardJson)->not->toContain('web_app');
});

it('rejects IP addresses as WebApp URLs to prevent Telegram API errors', function () {
    config()->set('app.url', 'https://185.120.10.15');

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);

    $urlMethod = $reflection->getMethod('getPublicWebAppUrl');
    $urlMethod->setAccessible(true);
    expect($urlMethod->invoke($controller))->toBeNull();

    $menuMethod = $reflection->getMethod('getReplyMainMenu');
    $menuMethod->setAccessible(true);
    $keyboardJson = json_encode($menuMethod->invoke($controller, 123456)->toArray());

    expect($keyboardJson)->not->toContain('web_app');
});

it('includes is_persistent in reply keyboard so keyboard remains available', function () {
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $menuMethod = $reflection->getMethod('getReplyMainMenu');
    $menuMethod->setAccessible(true);

    $keyboardArray = $menuMethod->invoke($controller, 123456)->toArray();

    expect($keyboardArray['is_persistent'] ?? null)->toBeTrue();
});

it('allows disabling web_app button in getReplyMainMenu for safe retry fallback', function () {
    config()->set('app.url', 'https://example.com');

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $menuMethod = $reflection->getMethod('getReplyMainMenu');
    $menuMethod->setAccessible(true);

    $withWebApp = json_encode($menuMethod->invoke($controller, 123456, true)->toArray());
    expect($withWebApp)->toContain('web_app');

    $withoutWebApp = json_encode($menuMethod->invoke($controller, 123456, false)->toArray());
    expect($withoutWebApp)->not->toContain('web_app');
});

it('includes reseller and site credentials buttons in inline main menu keyboard', function () {
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('getMainMenuKeyboard');
    $method->setAccessible(true);

    $keyboardJson = json_encode($method->invoke($controller)->toArray());
    expect($keyboardJson)->toContain('🏢 نمایندگی');
    expect($keyboardJson)->toContain('🔐 اطلاعات ورود به سایت');
});
