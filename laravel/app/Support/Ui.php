<?php

namespace App\Support;

/** Shared class strings, ported from client/src/components/ui.tsx. */
class Ui
{
    public const CONTROL = 'w-full rounded-lg border border-[#e4ddd2] bg-white px-3 py-2 text-sm outline-none focus:border-[#c4622d]';

    public const BUTTON_TONES = [
        'primary' => 'bg-[#c4622d] text-white hover:bg-[#a85122]',
        'navy' => 'bg-[#1a3c34] text-[#f6f1e8] hover:bg-[#142e28]',
        'ghost' => 'bg-white text-[#24312c] border border-[#e4ddd2] hover:bg-[#fbf8f3]',
        'danger' => 'bg-white text-[#9f2d2d] border border-[#e7c7c7] hover:bg-[#fdf6f6]',
    ];

    public const BADGE_TONES = [
        'slate' => 'bg-[#f3efe6] text-[#4a524c]',
        'green' => 'bg-[#e7f0ea] text-[#1a3c34]',
        'amber' => 'bg-[#f8f0df] text-[#8c6a32]',
        'red' => 'bg-[#fdf1ee] text-[#9f2d2d]',
        'blue' => 'bg-[#f6efe3] text-[#8c6a32]',
    ];
}
