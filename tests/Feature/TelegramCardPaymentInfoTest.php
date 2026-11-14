<?php

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

uses(RefreshDatabase::class);

/**
 * Capture the outgoing Telegram payloads instead of hitting the network.
 */
function captureCardTelegram(): ArrayObject
{
    $calls = new ArrayObject();

    $handler = function ($request, $options) use ($calls) {
        parse_str((string) $request->getBody(), $payload);
        $calls->append($payload);

        return Create::promiseFor(new PsrResponse(200, [], json_encode([
            'ok' => true,
            'result' => ['message_id' => $calls->count(), 'chat' => ['id' => 1], 'date' => time(), 'text' => $payload['text'] ?? ''],
        ])));
    };

    config([
        'telegram.http_client_handler' => new GuzzleHttpClient(
            new GuzzleClient(['handler' => HandlerStack::create($handler)])
        ),
    ]);

    return $calls;
}

function cardSettings(string $instructions): void
{
    Setting::updateOrCreate(['key' => 'telegram_bot_token'], ['value' => 'test-bot-token']);
    Setting::updateOrCreate(['key' => 'payment_cards'], ['value' => json_encode([
        ['card_number' => '6037-9977-1234-5678', 'card_holder' => 'شرکت مهرگان'],
    ])]);
    Setting::updateOrCreate(['key' => 'payment_card_instructions'], ['value' => $instructions]);
}

function callCardPaymentInfo(User $user, Order $order): void
{
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();

    $settingsProperty = new ReflectionProperty($controller, 'settings');
    $settingsProperty->setAccessible(true);
    $settingsProperty->setValue(
        $controller,
        Setting::all()->pluck('value', 'key')->map(fn ($value) => Setting::normalizeValue($value))
    );

    $method = new ReflectionMethod($controller, 'sendCardPaymentInfo');
    $method->setAccessible(true);
    $method->invoke($controller, (int) $user->telegram_chat_id, $order->id, null);
}

function pendingCardOrder(User $user): Order
{
    return $user->orders()->create([
        'plan_id' => null,
        'status' => 'pending',
        'source' => 'web',
        'amount' => 150000,
    ]);
}

it('shows the admin payment instructions in the Telegram card message', function () {
    $instructions = 'لطفاً پس از واریز، تصویر رسید را با نام خودتان ارسال کنید';
    cardSettings($instructions);

    $calls = captureCardTelegram();
    $user = User::factory()->create(['telegram_chat_id' => '123456789']);
    $order = pendingCardOrder($user);

    callCardPaymentInfo($user, $order);

    $text = $calls[0]['text'] ?? '';

    expect($text)->toContain($instructions)
        ->and($text)->toContain('6037\-9977\-1234\-5678')
        ->and($text)->toContain('شرکت مهرگان')
        ->and($user->fresh()->bot_state)->toBe('waiting_receipt_' . $order->id);
});

it('keeps multi-line instructions intact', function () {
    $instructions = "خط اول توضیحات\nخط دوم توضیحات";
    cardSettings($instructions);

    $calls = captureCardTelegram();
    $user = User::factory()->create(['telegram_chat_id' => '123456789']);
    $order = pendingCardOrder($user);

    callCardPaymentInfo($user, $order);

    expect($calls[0]['text'] ?? '')->toContain($instructions);
});

it('normalizes a legacy JSON-encoded instructions value', function () {
    cardSettings(json_encode('متن ذخیره‌شده به صورت JSON'));

    $calls = captureCardTelegram();
    $user = User::factory()->create(['telegram_chat_id' => '123456789']);
    $order = pendingCardOrder($user);

    callCardPaymentInfo($user, $order);

    $text = $calls[0]['text'] ?? '';

    expect($text)->toContain('متن ذخیره‌شده به صورت JSON')
        ->and($text)->not->toContain('"متن ذخیره');
});

it('does not add an empty instructions block when none is configured', function () {
    cardSettings('');

    $calls = captureCardTelegram();
    $user = User::factory()->create(['telegram_chat_id' => '123456789']);
    $order = pendingCardOrder($user);

    callCardPaymentInfo($user, $order);

    expect($calls[0]['text'] ?? '')
        ->toContain('شماره کارت')
        ->not->toContain('📝');
});
