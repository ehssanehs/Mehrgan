<?php

use App\Models\SequentialNamingSetting;
use App\Services\ClientNamingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates sequential names with prefix', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'server1u', 'counter' => 0, 'is_enabled' => true]);

    $name1 = ClientNamingService::generate(1, 1);
    expect($name1)->toBe('server1u1');

    $name2 = ClientNamingService::generate(1, 2);
    expect($name2)->toBe('server1u2');

    $name3 = ClientNamingService::generate(1, 3);
    expect($name3)->toBe('server1u3');

    $setting->refresh();
    expect($setting->counter)->toBe(3);
});

it('never reuses numbers after deletion', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'server1u', 'counter' => 3, 'is_enabled' => true]);

    // Simulate deletion of server1u2 - counter should stay at 3
    // Next generated should be server1u4, not server1u2
    $name = ClientNamingService::generate(1, 4);
    expect($name)->toBe('server1u4');

    $setting->refresh();
    expect($setting->counter)->toBe(4);
});

it('restarts counter from 1 when prefix changes', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'server1u', 'counter' => 5, 'is_enabled' => true]);

    // Change prefix
    $newSetting = ClientNamingService::updateSettings(true, 'eu-');
    expect($newSetting->prefix)->toBe('eu-');
    expect($newSetting->counter)->toBe(0);

    $name1 = ClientNamingService::generate(1, 10);
    expect($name1)->toBe('eu-1');

    $name2 = ClientNamingService::generate(1, 11);
    expect($name2)->toBe('eu-2');
});

it('falls back to old naming when disabled', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'server1u', 'counter' => 0, 'is_enabled' => false]);

    $name = ClientNamingService::generate(5, 10);
    expect($name)->toBe('user-5-order-10');

    $custom = ClientNamingService::generate(5, 10, 'myCustomName');
    expect($custom)->toBe('myCustomName');
});

it('respects custom username over sequential', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'server1u', 'counter' => 0, 'is_enabled' => true]);

    $name = ClientNamingService::generate(1, 1, 'customUser123');
    expect($name)->toBe('customUser123');

    // Counter should not increment when custom is used
    $setting->refresh();
    expect($setting->counter)->toBe(0);
});

it('can reset counter', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'test-', 'counter' => 10, 'is_enabled' => true]);

    $reset = ClientNamingService::resetCounter();
    expect($reset->counter)->toBe(0);
    expect($reset->prefix)->toBe('test-');

    $name = ClientNamingService::generate(1, 1);
    expect($name)->toBe('test-1');
});

it('admin settings update handles prefix change', function () {
    $setting = SequentialNamingSetting::getSettings();
    $setting->update(['prefix' => 'old-', 'counter' => 5, 'is_enabled' => true]);

    // Same prefix should keep counter
    $same = ClientNamingService::updateSettings(true, 'old-');
    expect($same->counter)->toBe(5);

    // Different prefix should reset
    $different = ClientNamingService::updateSettings(true, 'new-');
    expect($different->counter)->toBe(0);
});
