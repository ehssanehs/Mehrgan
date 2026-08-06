<?php

namespace Tests\Unit;

use App\Services\PasarGuardService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PasarGuardServiceTest extends TestCase
{
    public function test_pasarguard_create_user_assigns_all_available_groups(): void
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/groups/simple*' => Http::response([
                'groups' => [
                    ['id' => 1, 'name' => 'Default'],
                    ['id' => 2, 'name' => 'Premium'],
                ],
                'total' => 2,
            ], 200),
            'https://node1.example.com/api/user' => Http::response([
                'username' => 'testuser',
                'group_ids' => [1, 2],
                'subscription_url' => '/sub/testuser',
            ], 201),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');

        $response = $service->createUser([
            'username' => 'testuser',
            'expire' => 1700000000,
            'data_limit' => 1073741824,
        ]);

        $this->assertNotNull($response);
        $this->assertEquals('testuser', $response['username']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() === 'https://node1.example.com/api/user' && $request->method() === 'POST') {
                $data = json_decode($request->body(), true);

                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds'])
                    && !isset($data['group_id'])
                    && ($data['group_ids'] ?? null) === [1, 2]
                    && ($data['status'] ?? null) === 'active'
                    && ($data['data_limit_reset_strategy'] ?? null) === 'no_reset';
            }

            return false;
        });
    }

    public function test_pasarguard_create_user_preserves_explicit_group_ids_without_group_lookup(): void
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/user' => Http::response([
                'username' => 'testuser',
                'group_ids' => [1, 2],
                'subscription_url' => '/sub/testuser',
            ], 201),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');

        $response = $service->createUser([
            'username' => 'testuser',
            'expire' => 1700000000,
            'data_limit' => 1073741824,
            'group_ids' => [1, '2', 0, -1, 2],
        ]);

        $this->assertNotNull($response);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() === 'https://node1.example.com/api/user' && $request->method() === 'POST') {
                $data = json_decode($request->body(), true);

                return ($data['group_ids'] ?? null) === [1, 2]
                    && !isset($data['group_id']);
            }

            return false;
        });
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/api/groups'));
    }

    public function test_pasarguard_falls_back_to_legacy_groups_endpoint(): void
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/groups/simple*' => Http::response(['detail' => 'Not found'], 404),
            'https://node1.example.com/api/groups*' => Http::response([
                'groups' => [
                    ['id' => 3, 'name' => 'Enabled', 'is_disabled' => false],
                    ['id' => 4, 'name' => 'Disabled', 'is_disabled' => true],
                ],
                'total' => 2,
            ], 200),
            'https://node1.example.com/api/user' => Http::response([
                'username' => 'legacy-user',
                'subscription_url' => '/sub/legacy-user',
            ], 201),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');
        $response = $service->createUser(['username' => 'legacy-user']);

        $this->assertSame('legacy-user', $response['username']);
        Http::assertSent(function (Request $request): bool {
            if ($request->url() === 'https://node1.example.com/api/user' && $request->method() === 'POST') {
                return json_decode($request->body(), true)['group_ids'] === [3];
            }

            return false;
        });
    }

    public function test_pasarguard_does_not_create_an_unusable_user_when_no_groups_exist(): void
    {
        Http::fake([
            'https://node1.example.com/api/admin/token' => Http::response(['access_token' => 'test-token'], 200),
            'https://node1.example.com/api/groups/simple*' => Http::response(['groups' => [], 'total' => 0], 200),
        ]);

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');
        $response = $service->createUser(['username' => 'testuser']);

        $this->assertSame('pasarguard_groups_empty', $response['code']);
        Http::assertNotSent(fn (Request $request): bool =>
            $request->url() === 'https://node1.example.com/api/user' && $request->method() === 'POST'
        );
    }

    public function test_pasarguard_subscription_link_generation(): void
    {
        $service = new PasarGuardService('https://panel.example.com:2053', 'admin', 'password', 'sub.example.com');
        $link = $service->generateSubscriptionLink(['subscription_url' => '/sub/abc123xyz']);
        $this->assertEquals('https://sub.example.com/sub/abc123xyz', $link);

        // Fallback to baseUrl when nodeHostname is empty
        $service2 = new PasarGuardService('https://panel.example.com:2053', 'admin', 'password', '');
        $link2 = $service2->generateSubscriptionLink(['subscription_url' => '/sub/abc123xyz']);
        $this->assertEquals('https://panel.example.com:2053/sub/abc123xyz', $link2);

        // Full URL
        $link3 = $service2->generateSubscriptionLink(['subscription_url' => 'https://custom.com/sub/abc123xyz']);
        $this->assertEquals('https://custom.com/sub/abc123xyz', $link3);
    }

    public function test_pasarguard_update_preserves_existing_groups(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://node1.example.com/api/admin/token') {
                return Http::response(['access_token' => 'test-token'], 200);
            }

            if ($request->url() === 'https://node1.example.com/api/user/testuser' && $request->method() === 'GET') {
                return Http::response(['username' => 'testuser', 'group_ids' => [9]], 200);
            }

            if ($request->url() === 'https://node1.example.com/api/user/testuser' && $request->method() === 'PUT') {
                return Http::response(['success' => true], 200);
            }

            return Http::response([], 404);
        });

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');

        $response = $service->updateUser('testuser', [
            'expire' => 1700000000,
            'data_limit' => 2147483648,
            'proxies' => ['vless' => new \stdClass()],
        ]);

        $this->assertNotNull($response);

        Http::assertSent(function (Request $request): bool {
            if ($request->method() === 'PUT' && str_contains($request->url(), '/api/user/testuser')) {
                $data = $request->data();

                return isset($data['proxy_settings'])
                    && !isset($data['proxies'])
                    && !isset($data['inbounds'])
                    && !isset($data['group_ids']);
            }

            return false;
        });
    }

    public function test_pasarguard_update_repairs_a_user_with_no_groups(): void
    {
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://node1.example.com/api/admin/token') {
                return Http::response(['access_token' => 'test-token'], 200);
            }

            if (str_starts_with($request->url(), 'https://node1.example.com/api/groups/simple')) {
                return Http::response([
                    'groups' => [
                        ['id' => 5, 'name' => 'Main'],
                        ['id' => 6, 'name' => 'Backup'],
                    ],
                    'total' => 2,
                ], 200);
            }

            if ($request->url() === 'https://node1.example.com/api/user/testuser' && $request->method() === 'GET') {
                return Http::response(['username' => 'testuser', 'group_ids' => []], 200);
            }

            if ($request->url() === 'https://node1.example.com/api/user/testuser' && $request->method() === 'PUT') {
                return Http::response(['username' => 'testuser', 'group_ids' => [5, 6]], 200);
            }

            return Http::response([], 404);
        });

        $service = new PasarGuardService('https://node1.example.com', 'admin', 'password', 'node1.example.com');
        $response = $service->updateUser('testuser', ['data_limit' => 2147483648]);

        $this->assertSame([5, 6], $response['group_ids']);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://node1.example.com/api/user/testuser'
                && ($request->data()['group_ids'] ?? null) === [5, 6];
        });
    }
}
