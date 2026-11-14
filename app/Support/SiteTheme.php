<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Central registry of the site themes.
 *
 * The selectable "active_theme" setting drives the look of the public site
 * (see public/themes/shared/theme-shell.css and the layouts in
 * resources/views/layouts). This class exposes the same palette to the rest
 * of the application — most notably the Filament admin panel — so the
 * selected theme can be applied there as well.
 */
class SiteTheme
{
    public const DEFAULT_THEME = 'arcane';

    /**
     * Every palette mirrors the CSS variables defined for the same theme in
     * public/themes/shared/theme-shell.css.
     *
     *  - dark:          whether the theme is a dark theme (forces the admin
     *                   panel dark/light mode accordingly).
     *  - bg:            page background color of the theme (--app-bg).
     *  - accent:        theme accent color (--app-accent).
     *  - admin_primary: hex color used for the Filament panel primary color.
     *                   A readable (darker) variant of the accent is chosen
     *                   because Filament renders white text on it.
     *  - admin_gray:    base hex color used to generate the Filament gray
     *                   scale, so panel surfaces are tinted like the theme.
     *                   null keeps the default Filament gray palette.
     */
    protected const PALETTES = [
        'welcome' => [
            'dark' => true,
            'bg' => '#121212',
            'accent' => '#4ade80',
            'admin_primary' => '#059669',
            'admin_gray' => '#1e293b',
        ],
        'rocket' => [
            'dark' => true,
            'bg' => '#0b0b12',
            'accent' => '#ff3c00',
            'admin_primary' => '#ea580c',
            'admin_gray' => '#14141e',
        ],
        'arcane' => [
            'dark' => true,
            'bg' => '#020412',
            'accent' => '#00ffc2',
            'admin_primary' => '#0d9488',
            'admin_gray' => '#090e1f',
        ],
        'cyberpunk' => [
            'dark' => true,
            'bg' => '#0a0a1a',
            'accent' => '#00f6ff',
            'admin_primary' => '#c026d3',
            'admin_gray' => '#0f0f23',
        ],
        'dragon' => [
            'dark' => true,
            'bg' => '#100505',
            'accent' => '#e6b800',
            'admin_primary' => '#b45309',
            'admin_gray' => '#1e0a0a',
        ],
        'phoenix' => [
            'dark' => true,
            'bg' => '#0a0a0a',
            'accent' => '#ffd700',
            'admin_primary' => '#ea5800',
            'admin_gray' => '#1e0f05',
        ],
        'nebula' => [
            'dark' => true,
            'bg' => '#03030a',
            'accent' => '#8b5cf6',
            'admin_primary' => '#7c3aed',
            'admin_gray' => '#0a061e',
        ],
        'aurora' => [
            'dark' => false,
            'bg' => '#f8f9fb',
            'accent' => '#0ea5e9',
            'admin_primary' => '#0284c7',
            'admin_gray' => null,
        ],
        'obsidian' => [
            'dark' => true,
            'bg' => '#060608',
            'accent' => '#e6b84d',
            'admin_primary' => '#b45309',
            'admin_gray' => '#0f0f18',
        ],
    ];

    /**
     * All known theme keys (same list used by the layouts and ThemeSettings).
     *
     * @return array<int, string>
     */
    public static function available(): array
    {
        return array_keys(static::PALETTES);
    }

    /**
     * The currently selected theme key. Defensive on purpose: this can be
     * evaluated very early (panel boot, console commands) where the database
     * or the settings table may not be reachable yet.
     */
    public static function active(): string
    {
        try {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                // Avoid hitting the database from arbitrary console commands.
                if (! Schema::hasTable('settings')) {
                    return static::DEFAULT_THEME;
                }
            }

            $theme = setting('active_theme', static::DEFAULT_THEME);

            if (is_string($theme) && in_array($theme, static::available(), true)) {
                return $theme;
            }
        } catch (Throwable $e) {
            // Database/cache not available yet — fall back to the default.
        }

        return static::DEFAULT_THEME;
    }

    /**
     * The palette of the given (or currently active) theme.
     *
     * @return array{dark: bool, bg: string, accent: string, admin_primary: string, admin_gray: string|null}
     */
    public static function palette(?string $theme = null): array
    {
        $theme ??= static::active();

        return static::PALETTES[$theme] ?? static::PALETTES[static::DEFAULT_THEME];
    }

    /**
     * Whether the given (or currently active) theme is a dark theme.
     */
    public static function isDark(?string $theme = null): bool
    {
        return (bool) static::palette($theme)['dark'];
    }

    /**
     * Colors handed to the Filament panel via `->colors()`. Filament expands
     * each hex value into a full 50-950 shade scale automatically
     * (see \Filament\Support\Colors\Color::hex()).
     *
     * A single dark hex value (the old behaviour) produced a scale whose
     * mid-tones — gray-400/500, used by Filament for secondary text such as
     * sidebar group headings, stat labels and the user menu — were nearly the
     * same colour as the panel background (contrast ≈ 2.8:1). Dark themes
     * therefore get an explicit scale: readable text tones plus surfaces that
     * keep the theme tint.
     *
     * @return array<string, string|array<int, string>>
     */
    public static function filamentColors(?string $theme = null): array
    {
        $palette = static::palette($theme);

        $colors = [
            'primary' => $palette['admin_primary'],
        ];

        // Light themes keep a neutral gray scale.
        if ($palette['admin_gray'] === null) {
            $colors['gray'] = \Filament\Support\Colors\Color::Slate;

            return $colors;
        }

        $surface = $palette['admin_gray'];

        $colors['gray'] = [
            // Text tones: readable on the near-black surfaces of every dark theme.
            50 => '#f8fafc',
            100 => '#f1f5f9',
            200 => '#e2e8f0',
            300 => '#cbd5e1',
            400 => '#aab2c0', // secondary text (was ≈ #535662)
            500 => '#8d95a3', // muted text / labels
            600 => '#5d6473', // icons, disabled text
            // Surfaces: derived from the theme tint so the panel stays cohesive.
            700 => static::mix($surface, '#ffffff', 0.14),
            800 => static::mix($surface, '#ffffff', 0.07),
            900 => $surface,
            950 => $palette['bg'],
        ];

        return $colors;
    }

    /**
     * Blend two hex colours.
     *
     * @param string $from Base colour (e.g. #090e1f)
     * @param string $to   Colour to mix in (e.g. #ffffff)
     * @param float  $ratio 0 = $from, 1 = $to
     */
    protected static function mix(string $from, string $to, float $ratio): string
    {
        $parse = static function (string $hex): array {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        };

        [$fr, $fg, $fb] = $parse($from);
        [$tr, $tg, $tb] = $parse($to);

        $channel = static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $ratio);

        return sprintf('#%02x%02x%02x', $channel($fr, $tr), $channel($fg, $tg), $channel($fb, $tb));
    }

    /**
     * Small HTML snippet injected into the admin panel <head> to finish the
     * theming: sets the browser color-scheme and the panel page background
     * to the active theme background.
     */
    public static function adminPanelHeadHtml(): HtmlString
    {
        $palette = static::palette();

        $colorScheme = $palette['dark'] ? 'dark' : 'light';
        $bg = htmlspecialchars($palette['bg'], ENT_QUOTES, 'UTF-8');

        $css = <<<HTML
        <style id="site-theme-admin">
            html.fi { color-scheme: {$colorScheme}; }
            /* Override Filament's default bg-gray-50 / dark:bg-gray-950 body. */
            html.fi body.fi-body { background-color: {$bg} !important; }
        </style>
        HTML;

        return new HtmlString($css);
    }
}
