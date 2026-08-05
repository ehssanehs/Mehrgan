<?php

use App\Models\User;

it('bans a user with reason and clears bot state', function () {
    $user = User::factory()->create(['bot_state' => 'awaiting_deposit_amount']);

    $user->ban('تخلف در استفاده از سرویس', 1);

    expect($user->fresh()->isBanned())->toBeTrue()
        ->and($user->fresh()->ban_reason)->toBe('تخلف در استفاده از سرویس')
        ->and($user->fresh()->banned_at)->not->toBeNull()
        ->and($user->fresh()->banned_by)->toBe(1)
        ->and($user->fresh()->bot_state)->toBeNull();
});

it('unbans a user and clears ban fields', function () {
    $user = User::factory()->create();
    $user->ban('دلیل تست', 1);

    $user->unban();

    expect($user->fresh()->isBanned())->toBeFalse()
        ->and($user->fresh()->ban_reason)->toBeNull()
        ->and($user->fresh()->banned_at)->toBeNull()
        ->and($user->fresh()->banned_by)->toBeNull();
});

it('provides banned and not-banned scopes', function () {
    User::factory()->create();
    $banned = User::factory()->create();
    $banned->ban('تست');

    expect(User::banned()->pluck('id'))->toContain($banned->id)
        ->and(User::notBanned()->count())->toBe(1);
});

it('does not allow banned users to log in to the site', function () {
    $user = User::factory()->create();
    $user->ban('تخلف');

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs out banned users from active sessions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk();

    $user->ban('تخلف');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
