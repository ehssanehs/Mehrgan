<?php

use App\Models\Setting;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\MultiServer\Models\Location;
use Modules\MultiServer\Models\Server;
use Telegram\Bot\HttpClients\GuzzleHttpClient;

uses(RefreshDatabase::class);

/**
 * Capture every outbound Telegram API call instead of hitting the network.
 */
function fakeTelegramTransport(): ArrayObject
{
    $calls = new ArrayObject();

    $handler = function ($request, $options) use ($calls) {
        $raw = (string) $request->getBody();
        parse_str($raw, $body);
        $calls->append($body);

        return Create::promiseFor(new PsrResponse(200, [], json_encode([
            'ok' => true,
            'result' => ['message_id' => $calls->count(), 'chat' => ['id' => 1], 'date' => time(), 'text' => $body['text'] ?? ''],
        ])));
    };

    config([
        'telegram.http_client_handler' => new GuzzleHttpClient(
            new GuzzleClient(['handler' => HandlerStack::create($handler)])
        ),
    ]);

    return $calls;
}

function trialSettings(array $overrides = []): void
{
    $base = [
        'telegram_bot_token' => 'test-bot-token',
        'panel_type' => 'xui',
        'enable_multilocation' => '1',
        'trial_enabled' => '1',
        'trial_volume_mb' => '500',
        'trial_duration_hours' => '24',
        'trial_limit_per_user' => '1',
        'xui_link_type' => 'single',
    ];

    foreach (array_merge($base, $overrides) as $key => $value) {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

function xuiServer(array $overrides = []): Server
{
    $location = Location::create([
        'name' => 'Germany',
        'slug' => 'germany-' . uniqid(),
        'flag' => '🇩🇪',
        'is_active' => true,
    ]);

    return Server::create(array_merge([
        'location_id' => $location->id,
        'type' => 'xui',
        'name' => 'Server Germany 1',
        'ip_address' => '1.2.3.4',
        'port' => 2053,
        'username' => 'admin',
        'password' => 'secret',
        'is_https' => true,
        'path' => '/',
        'inbound_id' => 1,
        'capacity' => 100,
        'current_users' => 0,
        'is_active' => true,
        'link_type' => 'single',
    ], $overrides));
}

function fakeXuiPanel(): void
{
    Http::fake([
        'https://1.2.3.4:2053/*login*' => Http::response(['success' => true], 200),
        'https://1.2.3.4:2053/*panel/api/inbounds/list' => Http::response([
            'success' => true,
            'obj' => [[
                'id' => 1,
                'remark' => 'Trial Inbound',
                'protocol' => 'vless',
                'port' => 443,
                'listen' => '',
                'streamSettings' => json_encode(['network' => 'ws', 'security' => 'tls', 'wsSettings' => ['path' => '/ws', 'headers' => ['Host' => 'cdn.example.com']]]),
            ]],
        ], 200),
        'https://1.2.3.4:2053/*panel/api/inbounds/addClient' => Http::response([
            'success' => true,
            'msg' => 'Client added',
        ], 200),
    ]);
}

function callTrialRequest(User $user): void
{
    $controller = new \Modules\TelegramBot\Http\Controllers\WebhookController();

    $settingsProperty = new ReflectionProperty($controller, 'settings');
    $settingsProperty->setAccessible(true);
    $settingsProperty->setValue(
        $controller,
        Setting::all()->pluck('value', 'key')->map(fn ($value) => Setting::normalizeValue($value))
    );

    $method = new ReflectionMethod($controller, 'handleTrialRequest');
    $method->setAccessible(true);
    $method->invoke($controller, $user);
}

function sentTexts(ArrayObject $calls): array
{
    return array_map(fn ($call) => $call['text'] ?? '', $calls->getArrayCopy());
}

it('creates a trial account on an active multi-server X-UI server', function () {
    trialSettings();
    xuiServer();
    fakeXuiPanel();
    $calls = fakeTelegramTransport();

    $user = User::factory()->create(['telegram_chat_id' => '123456789']);

    callTrialRequest($user);

    $texts = sentTexts($calls);

    expect($texts)->toHaveCount(1)
        ->and($texts[0])->toContain('اکانت تست شما با موفقیت ساخته شد')
        ->and($user->fresh()->trial_accounts_taken)->toBe(1);
});

it('uses the configured multi-server even when the legacy multilocation flag was never saved', function () {
    // Installations that only configure servers under "Multi Server" never save
    // `enable_multilocation`; the trial flow must not depend on that flag.
    trialSettings();
    Setting::where('key', 'enable_multilocation')->delete();

    xuiServer();
    fakeXuiPanel();
    $calls = fakeTelegramTransport();

    $user = User::factory()->create(['telegram_chat_id' => '123456789']);

    callTrialRequest($user);

    $texts = sentTexts($calls);

    expect($texts)->toHaveCount(1)
        ->and($texts[0])->toContain('اکانت تست شما با موفقیت ساخته شد')
        ->and($user->fresh()->trial_accounts_taken)->toBe(1);
});

it('prefers the server selected in the trial settings', function () {
    trialSettings();
    $selected = xuiServer(['name' => 'Trial Server', 'ip_address' => '5.6.7.8']);
    xuiServer(['name' => 'Other Server', 'ip_address' => '9.9.9.9']);
    Setting::updateOrCreate(['key' => 'trial_server_id'], ['value' => (string) $selected->id]);

    Http::fake([
        'https://5.6.7.8:2053/*login*' => Http::response(['success' => true], 200),
        'https://5.6.7.8:2053/*panel/api/inbounds/list' => Http::response([
            'success' => true,
            'obj' => [[
                'id' => 1,
                'remark' => 'Trial Inbound',
                'protocol' => 'vless',
                'port' => 443,
                'listen' => '',
                'streamSettings' => json_encode(['network' => 'tcp', 'security' => 'none']),
            ]],
        ], 200),
        'https://5.6.7.8:2053/*panel/api/inbounds/addClient' => Http::response(['success' => true], 200),
    ]);

    $calls = fakeTelegramTransport();
    $user = User::factory()->create(['telegram_chat_id' => '123456789']);

    callTrialRequest($user);

    expect(sentTexts($calls)[0] ?? '')->toContain('اکانت تست شما با موفقیت ساخته شد');
});

it('creates a trial account on a Marzban server even when the global panel type differs', function () {
    // The global `panel_type` still points at the legacy X-UI settings; the
    // server selected from the MultiServer module must win.
    trialSettings(['panel_type' => 'xui']);
    xuiServer([
        'type' => 'marzban',
        'name' => 'Marzban DE',
        'ip_address' => '2.2.2.2',
        'port' => 8000,
        'inbound_id' => null,
    ]);

    Http::fake([
        'https://2.2.2.2:8000/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
        'https://2.2.2.2:8000/api/user' => Http::response([
            'username' => 'trial-1-1',
            'subscription_url' => '/sub/trial-1-1',
            'expire' => now()->addDay()->timestamp,
            'data_limit' => 524288000,
        ], 200),
    ]);

    $calls = fakeTelegramTransport();
    $user = User::factory()->create(['telegram_chat_id' => '123456789']);

    callTrialRequest($user);

    expect(sentTexts($calls)[0] ?? '')->toContain('اکانت تست شما با موفقیت ساخته شد')
        ->and($user->fresh()->trial_accounts_taken)->toBe(1);

    $createRequest = collect(Http::recorded())
        ->map(fn ($pair) => $pair[0])
        ->first(fn ($request) => str_contains($request->url(), '/api/user'));

    expect($createRequest)->not->toBeNull()
        ->and($createRequest->body())->toContain('"username":"trial-' . $user->id . '-1"')
        ->and($createRequest->body())->toContain('"data_limit":524288000');
});
