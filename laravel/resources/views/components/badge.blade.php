@props(['tone' => 'slate', 'status' => null])
@php($tone = $status !== null ? \App\Support\Format::tone($status) : $tone)
<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '.\App\Support\Ui::BADGE_TONES[$tone]]) }}>{{ $slot->isEmpty() && $status !== null ? \App\Support\Format::title($status) : $slot }}</span>
