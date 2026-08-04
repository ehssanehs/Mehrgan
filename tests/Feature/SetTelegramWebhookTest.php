<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('sets the webhook with a POST request and a trimmed token from site settings', function () {
    config()->set('app.url', 'https://vpn.example.test/');

    $token = '123456:ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghi';
    Setting::create(['key' => 'telegram_bot_token', 'value' => " bot{$token} \n"]);

    Http::fake([
        "https://api.telegram.org/bot{$token}/setWebhook" => Http::response([
            'ok' => true,
            'description' => 'Webhook was set',
        ]),
    ]);

    $this->artisan('telegram:set-webhook')
        ->expectsOutput('Setting webhook to: https://vpn.example.test/webhooks/telegram')
        ->expectsOutput('Webhook set successfully! Description: Webhook was set')
        ->assertExitCode(0);

    Http::assertSent(function (Request $request) use ($token): bool {
        return $request->method() === 'POST'
            && $request->url() === "https://api.telegram.org/bot{$token}/setWebhook"
            && $request['url'] === 'https://vpn.example.test/webhooks/telegram';
    });
});

it('fails with clear guidance instead of calling Telegram when the token is invalid', function () {
    config()->set('app.url', 'https://vpn.example.test');
    Setting::create(['key' => 'telegram_bot_token', 'value' => 'not-a-token']);

    $this->artisan('telegram:set-webhook')
        ->expectsOutput('Error: the Telegram bot token is missing or invalid. Enter the token from BotFather in Site Settings and try again.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});
