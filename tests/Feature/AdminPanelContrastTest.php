<?php

use App\Support\SiteTheme;

/**
 * WCAG 2.x relative luminance for a #rrggbb colour.
 */
function relativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    $channels = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

    $linear = array_map(function (int $value): float {
        $value /= 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, $channels);

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

function contrastRatio(string $foreground, string $background): float
{
    $a = relativeLuminance($foreground);
    $b = relativeLuminance($background);

    $lighter = max($a, $b);
    $darker = min($a, $b);

    return ($lighter + 0.05) / ($darker + 0.05);
}

it('keeps admin panel secondary text readable on every dark theme', function () {
    $checked = 0;

    foreach (SiteTheme::available() as $theme) {
        $palette = SiteTheme::palette($theme);

        // Light themes use Filament's neutral Slate scale, whose text tones are
        // dark on a light background.
        if ($palette['admin_gray'] === null) {
            continue;
        }

        $gray = SiteTheme::filamentColors($theme)['gray'];
        $background = $palette['bg'];

        // gray-400 is what Filament uses for secondary text in dark mode
        // (sidebar group headings, stat labels, user menu); gray-500 for muted
        // labels. Both must stay comfortably above the WCAG AA 4.5:1 minimum —
        // the previous single-hex scale produced ≈ 2.8:1, which was unreadable.
        expect(contrastRatio($gray[400], $background))
            ->toBeGreaterThanOrEqual(4.5, "gray-400 on the {$theme} theme background");
        expect(contrastRatio($gray[500], $background))
            ->toBeGreaterThanOrEqual(4.5, "gray-500 on the {$theme} theme background");

        $checked++;
    }

    expect($checked)->toBeGreaterThan(0);
});

it('keeps the panel surfaces on the dark side of the scale', function () {
    foreach (SiteTheme::available() as $theme) {
        $palette = SiteTheme::palette($theme);

        if ($palette['admin_gray'] === null) {
            continue;
        }

        $gray = SiteTheme::filamentColors($theme)['gray'];

        // Surfaces must get darker as the shade increases, and stay tinted by
        // the theme instead of falling back to a neutral gray.
        expect(relativeLuminance($gray[700]))->toBeGreaterThan(relativeLuminance($gray[800]));
        expect(relativeLuminance($gray[800]))->toBeGreaterThan(relativeLuminance($gray[900]));
        expect(relativeLuminance($gray[900]))->toBeGreaterThan(relativeLuminance($gray[950]));
        expect(strtolower($gray[900]))->toBe(strtolower($palette['admin_gray']));
    }
});
