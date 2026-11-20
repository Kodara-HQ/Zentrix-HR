<?php

namespace App\Support;

use App\Models\Company;

/**
 * A company's colours. The system administrator picks two: the main colour (sidebar and dark accents)
 * and the accent colour (buttons, links, highlights). Lighter and darker shades are derived in CSS, and
 * both colours must be dark enough for white text on top of them.
 */
class Theme
{
    public const DEFAULT_PRIMARY = '#1b2b57';

    public const DEFAULT_ACCENT = '#1570c4';

    /** Ready-made pairs offered on the company form: name => [main, accent]. */
    public const PRESETS = [
        'Zentrix navy' => ['#1b2b57', '#1570c4'],
        'Forest' => ['#173d2c', '#1f7a4d'],
        'Burgundy' => ['#4a1426', '#a3264b'],
        'Teal' => ['#123f45', '#0f7c83'],
        'Royal purple' => ['#2b1b52', '#6a3fc4'],
        'Charcoal and orange' => ['#25272b', '#b5530f'],
        'Cocoa and gold' => ['#3b2717', '#8f6410'],
        'Slate and red' => ['#22303c', '#c0352b'],
    ];

    /** Inline CSS that recolours the page for the company, or '' for the standard colours. */
    public static function css(?Company $company): string
    {
        $primary = self::valid($company?->theme_primary) ? strtolower($company->theme_primary) : self::DEFAULT_PRIMARY;
        $accent = self::valid($company?->theme_accent) ? strtolower($company->theme_accent) : self::DEFAULT_ACCENT;
        if ($primary === self::DEFAULT_PRIMARY && $accent === self::DEFAULT_ACCENT) {
            return '';
        }

        return ':root{'
            ."--color-navy:{$primary};"
            ."--color-navy-deep:color-mix(in oklab,{$primary} 82%,black);"
            ."--color-accent-strong:{$accent};"
            ."--color-accent-deep:color-mix(in oklab,{$accent} 80%,black);"
            ."--color-accent:color-mix(in oklab,{$accent} 72%,white);"
            ."--color-sky:color-mix(in oklab,{$accent} 10%,white);"
            ."--color-sky-text:color-mix(in oklab,{$accent} 24%,white);"
            .'}';
    }

    public static function valid(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /** WCAG contrast ratio between the colour and white (1 to 21). */
    public static function contrastWithWhite(string $hex): float
    {
        $channel = function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return 1.05 / ($luminance + 0.05);
    }

    /** Validation rule: a #rrggbb colour that white text stays readable on. */
    public static function rule(string $label): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($label) {
            if (! self::valid($value)) {
                $fail("{$label} must be a colour like #1570c4.");
            } elseif (self::contrastWithWhite($value) < 4.5) {
                $fail("{$label} is too light: white text on it would be hard to read. Choose a darker shade.");
            }
        };
    }
}
