@props(['label'])
<label {{ $attributes->merge(['class' => 'block text-sm']) }}>
    <span class="mb-1 block font-semibold text-body">{{ $label }}</span>
    {{ $slot }}
</label>
