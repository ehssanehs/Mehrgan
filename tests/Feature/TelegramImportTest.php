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

it('lists imported subscriptions in My Services even without a plan', function () {
    $user = User::factory()->create();

    // Imported subscription: import succeeds even when no active plan matched,
    // so plan_id is null. It must still appear in the bot's "My Services".
    $imported = Order::create([
        'user_id' => $user->id,
        'plan_id' => null,
        'status' => 'paid',
        'source' => 'telegram',
        'payment_method' => 'imported',
        'config_details' => 'vless://aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa@example.com:443#Imported',
        'expires_at' => now()->addYear(),
        'panel_username' => 'imported-user',
        'panel_client_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        'is_imported' => true,
        'import_meta' => ['panel_type' => 'xui', 'totalGB' => 53687091200], // 50 GB
    ]);

    // A regular paid order without a plan is not a service and must stay hidden.
    $planless = Order::create([
        'user_id' => $user->id,
        'plan_id' => null,
        'status' => 'paid',
        'source' => 'telegram',
        'payment_method' => 'wallet',
        'amount' => 100000,
        'config_details' => 'vless://bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb@example.com:443#X',
        'expires_at' => now()->addMonth(),
        'is_imported' => false,
    ]);

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $method = (new ReflectionClass($controller))->getMethod('getMyServicesOrders');
    $method->setAccessible(true);
    $orders = $method->invoke($controller, $user);

    expect($orders->pluck('id'))->toContain($imported->id);
    expect($orders->pluck('id'))->not->toContain($planless->id);
});

it('keeps imported subscriptions in My Services regardless of panel expiry', function () {
    $user = User::factory()->create();

    // An imported subscription whose panel expiry is long past must remain
    // visible in the bot, matching the web dashboard behaviour.
    $imported = Order::create([
        'user_id' => $user->id,
        'plan_id' => null,
        'status' => 'paid',
        'source' => 'telegram',
        'payment_method' => 'imported',
        'config_details' => 'vless://cccccccc-cccc-4ccc-8ccc-cccccccccccc@example.com:443#ExpiredImport',
        'expires_at' => now()->subDays(60),
        'panel_username' => 'old-import',
        'panel_client_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
        'is_imported' => true,
    ]);

    // A regular order expired more than 30 days ago must stay hidden.
    $regular = Order::create([
        'user_id' => $user->id,
        'plan_id' => \App\Models\Plan::factory()->create()->id,
        'status' => 'paid',
        'source' => 'telegram',
        'payment_method' => 'wallet',
        'amount' => 100000,
        'expires_at' => now()->subDays(60),
        'is_imported' => false,
    ]);

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $method = (new ReflectionClass($controller))->getMethod('getMyServicesOrders');
    $method->setAccessible(true);
    $orders = $method->invoke($controller, $user);

    expect($orders->pluck('id'))->toContain($imported->id);
    expect($orders->pluck('id'))->not->toContain($regular->id);
});

it('still lists regular paid services with a plan', function () {
    $user = User::factory()->create();
    $plan = \App\Models\Plan::factory()->create();

    $order = Order::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'paid',
        'source' => 'telegram',
        'payment_method' => 'wallet',
        'amount' => $plan->price,
        'config_details' => 'vless://dddddddd-dddd-4ddd-8ddd-dddddddddddd@example.com:443#Plan',
        'expires_at' => now()->addMonth(),
        'panel_username' => 'regular-user',
        'is_imported' => false,
    ]);

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $method = (new ReflectionClass($controller))->getMethod('getMyServicesOrders');
    $method->setAccessible(true);
    $orders = $method->invoke($controller, $user);

    expect($orders->pluck('id'))->toContain($order->id);
});

it('shows details of an imported subscription without a plan', function () {
    $user = User::factory()->create();

    $imported = Order::create([
        'user_id' => $user->id,
        'plan_id' => null,
        'status' => 'paid',
        'source' => 'telegram',
        'payment_method' => 'imported',
        'config_details' => 'vless://eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee@example.com:443#Imported',
        'expires_at' => now()->addYear(),
        'panel_username' => 'imported-user',
        'panel_client_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
        'is_imported' => true,
        'import_meta' => ['panel_type' => 'xui', 'totalGB' => 53687091200], // 50 GB
    ]);

    \Telegram\Bot\Laravel\Facades\Telegram::shouldReceive('sendMessage')
        ->once()
        ->with(\Mockery::on(function ($payload) {
            $text = $payload['text'] ?? '';
            return str_contains($text, 'اشتراک واردشده')
                && str_contains($text, 'imported-user')
                && str_contains($text, '50 گیگابایت');
        }))
        ->andReturn(true);

    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();
    $method = (new ReflectionClass($controller))->getMethod('showServiceDetails');
    $method->setAccessible(true);
    $method->invoke($controller, $user, $imported->id);
});
