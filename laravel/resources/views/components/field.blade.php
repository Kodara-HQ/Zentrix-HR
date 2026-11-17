@props(['label'])
<label {{ $attributes->merge(['class' => 'block text-sm']) }}>
    <span class="mb-1 block font-semibold text-[#4a524c]">{{ $label }}</span>
    {{ $slot }}
</label>
