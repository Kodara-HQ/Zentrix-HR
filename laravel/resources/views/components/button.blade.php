@props(['tone' => 'primary', 'small' => false, 'type' => 'button', 'href' => null])
@php
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-semibold disabled:opacity-50 '
        .($small ? 'px-2.5 py-1.5 text-sm ' : 'px-3.5 py-2 text-sm ')
        .\App\Support\Ui::BUTTON_TONES[$tone];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
