<?php

namespace App\Support;

/** Shared class strings, ported from client/src/components/ui.tsx. */
class Ui
{
    public const CONTROL = 'w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-accent-strong';

    public const BUTTON_TONES = [
        'primary' => 'bg-accent-strong text-white hover:bg-accent-deep',
        'navy' => 'bg-navy text-cream hover:bg-navy-deep',
        'ghost' => 'bg-white text-body border border-line hover:bg-cream',
        'danger' => 'bg-white text-[#9f2d2d] border border-[#e7c7c7] hover:bg-[#fdf6f6]',
    ];

    public const BADGE_TONES = [
        'slate' => 'bg-[#f1efe9] text-body',
        'green' => 'bg-[#e5f3ea] text-[#1f6b3d]',
        'amber' => 'bg-[#fbf1dc] text-[#8a5a12]',
        'red' => 'bg-[#fdecec] text-[#a12d2d]',
        'blue' => 'bg-sky text-navy',
    ];
}
