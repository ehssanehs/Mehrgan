<?php

use App\Models\Setting;
use App\Support\SiteTheme;
use Illuminate\Support\Facades\Cache;

/**
 * Guards for the admin simplification pass:
 *  - only the two maintained themes stay selectable,
 *  - each duplicated setting lives on exactly one page.
 *
 * The ownership checks read the page sources on purpose: Filament builds its
 * schema at runtime, and a plain "this key appears on one page only" assertion
 * is what keeps the duplication from creeping back.
 */
function formKeysIn(string $path): string
{
    return (string) file_get_contents(base_path($path));
}

it('offers only the two maintained themes', function () {
    expect(SiteTheme::available())->toBe(['arcane', 'aurora']);

    foreach (SiteTheme::available() as $theme) {
        expect(view()->exists("themes.{$theme}"))->toBeTrue();
        expect(is_dir(public_path("themes/{$theme}")))->toBeTrue();
    }
});

it('no longer ships the retired theme views', function () {
    foreach (['welcome', 'rocket', 'cyberpunk', 'dragon', 'phoenix', 'nebula', 'obsidian'] as $theme) {
        expect(view()->exists("themes.{$theme}"))->toBeFalse();
    }
});

it('falls back to the default theme when the stored one was removed', function () {
    Setting::updateOrCreate(['key' => 'active_theme'], ['value' => 'obsidian']);
    Cache::forget('settings');

    expect(SiteTheme::active())->toBe(SiteTheme::DEFAULT_THEME);
});

it('keeps every duplicated setting on a single page', function () {
    $siteSettings = formKeysIn('app/Filament/Pages/ThemeSettings.php');
    $trial = formKeysIn('app/Filament/Pages/ManageTrialSettings.php');
    $reseller = formKeysIn('app/Filament/Pages/ManageResellerSettings.php');
    $botContent = formKeysIn('Modules/TelegramBot/Filament/Resources/TelegramBotSettingResource/Pages/ManageTelegramBotSettings.php');

    // Referral rewards belong to the dedicated referral page.
    expect($siteSettings)->not->toContain('referral_welcome_gift')
        ->and($siteSettings)->not->toContain('referral_referrer_reward');

    // Bot menu visibility lives with the feature it toggles.
    expect($siteSettings)->not->toContain('tg_show_trial_button')
        ->and($siteSettings)->not->toContain('tg_show_reseller_button')
        ->and($trial)->toContain('tg_show_trial_button')
        ->and($reseller)->toContain('tg_show_reseller_button');

    // Reseller deposit cards are owned by the reseller settings page only.
    expect($botContent)->not->toContain('agent_deposit_cards')
        ->and($reseller)->toContain('agent_deposit_cards');
});

it('groups the admin navigation into a single shallow hierarchy', function () {
    $groups = [
        'app/Filament/Resources/OrderResource.php' => 'فروش',
        'app/Filament/Resources/UserResource.php' => 'کاربران',
        'Modules/Reseller/Filament/Resources/ResellerResource.php' => 'نمایندگی',
        'Modules/MultiServer/Filament/Resources/ServerResource.php' => 'سرورها',
        'Modules/Blog/Filament/Resources/BlogPostResource.php' => 'محتوا',
        'app/Filament/Pages/ThemeSettings.php' => 'تنظیمات',
    ];

    foreach ($groups as $path => $expected) {
        expect(formKeysIn($path))->toContain("navigationGroup = '{$expected}'");
    }
});
