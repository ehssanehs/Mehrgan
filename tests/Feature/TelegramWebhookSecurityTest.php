<?php

use App\Models\Setting;

beforeEach(function () {
    Setting::insert([
        ['key' => 'telegram_bot_token', 'value' => 'test-bot-token'],
        ['key' => 'telegram_webhook_secret', 'value' => 'super-secret-token'],
    ]);
});

it('rejects telegram updates that do not carry the secret header', function () {
    $this->postJson('/webhooks/telegram', ['update_id' => 1])
        ->assertStatus(403);
});

it('rejects telegram updates signed with a different secret', function () {
    $this->postJson('/webhooks/telegram', ['update_id' => 1], [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
    ])->assertStatus(403);
});

it('rejects every update when no secret has been registered yet', function () {
    Setting::where('key', 'telegram_webhook_secret')->delete();
    \Illuminate\Support\Facades\Cache::forget('settings');

    $this->postJson('/webhooks/telegram', ['update_id' => 1], [
        'X-Telegram-Bot-Api-Secret-Token' => 'anything',
    ])->assertStatus(403);
});

it('accepts a correctly signed update', function () {
    fakeTelegram();

    $this->postJson('/webhooks/telegram', ['update_id' => 99], [
        'X-Telegram-Bot-Api-Secret-Token' => 'super-secret-token',
    ])->assertStatus(200);
});
