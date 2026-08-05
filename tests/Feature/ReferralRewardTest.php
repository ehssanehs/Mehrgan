<?php

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Telegram\Bot\Laravel\Facades\Telegram;

it('credits and notifies the referrer when a referred user completes a paid order', function () {
    Setting::insert([
        ['key' => 'referral_enabled', 'value' => '1'],
        ['key' => 'referral_reward_only_first_purchase', 'value' => '1'],
        ['key' => 'referral_referrer_reward', 'value' => '15000'],
        ['key' => 'referral_referrer_reward_percent', 'value' => '0'],
        ['key' => 'referral_telegram_notify_referrer', 'value' => '1'],
        ['key' => 'telegram_bot_token', 'value' => 'test-bot-token'],
    ]);

    $referrer = User::factory()->create([
        'balance' => 20000,
        'telegram_chat_id' => '123456789',
    ]);
    $referredUser = User::factory()->create([
        'name' => 'Referred customer',
        'referrer_id' => $referrer->id,
    ]);
    $plan = Plan::factory()->create();
    $order = Order::create([
        'user_id' => $referredUser->id,
        'plan_id' => $plan->id,
        'status' => 'paid',
        'amount' => $plan->price,
        'source' => 'telegram',
        'payment_method' => 'wallet',
    ]);

    Telegram::shouldReceive('setAccessToken')
        ->once()
        ->with('test-bot-token');
    Telegram::shouldReceive('sendMessage')
        ->once()
        ->with(Mockery::on(fn (array $payload) => $payload['chat_id'] === '123456789'
            && str_contains($payload['text'], '15,000')));

    OrderPaid::dispatch($order);

    expect($referrer->fresh()->balance)->toBe(35000)
        ->and(Transaction::where('user_id', $referrer->id)
            ->where('order_id', $order->id)
            ->where('metadata->referral_reward', true)
            ->count())->toBe(1);
});
