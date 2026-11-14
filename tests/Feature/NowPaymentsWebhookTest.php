<?php

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function nowPaymentsSignature(array $payload, string $secret): string
{
    $sort = function (array $value) use (&$sort): array {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $sort($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    };

    return hash_hmac('sha512', json_encode($sort($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $secret);
}

function makeNowPaymentsOrder(): Order
{
    $user = User::factory()->create();

    return Order::create([
        'user_id' => $user->id,
        'plan_id' => null,
        'status' => 'pending',
        'amount' => 500000,
        'source' => 'web',
        'payment_method' => 'nowpayments',
    ]);
}

beforeEach(function () {
    config(['services.nowpayments.ipn_secret' => 'ipn-secret-for-tests']);
});

it('rejects an unsigned callback', function () {
    $order = makeNowPaymentsOrder();

    $this->postJson('/webhooks/nowpayments', [
        'order_id' => $order->id,
        'payment_status' => 'finished',
        'payment_id' => 'pay-1',
    ])->assertStatus(403);

    expect($order->fresh()->status)->toBe('pending');
});

it('rejects a callback signed with the wrong secret', function () {
    $order = makeNowPaymentsOrder();
    $payload = [
        'order_id' => $order->id,
        'payment_status' => 'finished',
        'payment_id' => 'pay-1',
    ];

    $this->postJson('/webhooks/nowpayments', $payload, [
        'x-nowpayments-sig' => nowPaymentsSignature($payload, 'some-other-secret'),
    ])->assertStatus(403);

    expect($order->fresh()->status)->toBe('pending');
});

it('marks the order paid and fires OrderPaid for a verified callback', function () {
    Event::fake([OrderPaid::class]);

    $order = makeNowPaymentsOrder();
    $payload = [
        'order_id' => $order->id,
        'payment_status' => 'finished',
        'payment_id' => 'pay-42',
        'price_amount' => 10,
    ];

    $this->postJson('/webhooks/nowpayments', $payload, [
        'x-nowpayments-sig' => nowPaymentsSignature($payload, 'ipn-secret-for-tests'),
    ])->assertStatus(200);

    $order->refresh();

    expect($order->status)->toBe('paid')
        ->and($order->nowpayments_payment_id)->toBe('pay-42');

    Event::assertDispatched(OrderPaid::class);
});

it('does not treat an in-flight status as paid', function () {
    $order = makeNowPaymentsOrder();
    $payload = [
        'order_id' => $order->id,
        'payment_status' => 'sending',
        'payment_id' => 'pay-9',
    ];

    $this->postJson('/webhooks/nowpayments', $payload, [
        'x-nowpayments-sig' => nowPaymentsSignature($payload, 'ipn-secret-for-tests'),
    ])->assertStatus(200);

    expect($order->fresh()->status)->toBe('pending');
});

it('is idempotent for repeated verified callbacks', function () {
    Event::fake([OrderPaid::class]);

    $order = makeNowPaymentsOrder();
    $payload = [
        'order_id' => $order->id,
        'payment_status' => 'confirmed',
        'payment_id' => 'pay-7',
    ];
    $headers = ['x-nowpayments-sig' => nowPaymentsSignature($payload, 'ipn-secret-for-tests')];

    $this->postJson('/webhooks/nowpayments', $payload, $headers)->assertStatus(200);
    $this->postJson('/webhooks/nowpayments', $payload, $headers)->assertStatus(200);

    Event::assertDispatchedTimes(OrderPaid::class, 1);
});

it('fails closed when no IPN secret is configured', function () {
    config(['services.nowpayments.ipn_secret' => null]);

    $order = makeNowPaymentsOrder();
    $payload = [
        'order_id' => $order->id,
        'payment_status' => 'finished',
        'payment_id' => 'pay-1',
    ];

    $this->postJson('/webhooks/nowpayments', $payload, [
        'x-nowpayments-sig' => nowPaymentsSignature($payload, ''),
    ])->assertStatus(503);

    expect($order->fresh()->status)->toBe('pending');
});
