@props(['name', 'value' => null, 'type' => 'text'])
<input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value) }}" {{ $attributes->merge(['class' => \App\Support\Ui::CONTROL]) }}>
