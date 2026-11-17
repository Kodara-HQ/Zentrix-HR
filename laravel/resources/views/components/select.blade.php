@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null, 'labels' => false])
{{-- options: [value => label]; with :labels="true" the values are enum-style keys shown in title case --}}
<select name="{{ $name }}" {{ $attributes->merge(['class' => \App\Support\Ui::CONTROL]) }}>
    @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
    @foreach ($options as $value => $label)
        @php($value = $labels ? $label : $value)
        <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $labels ? \App\Support\Format::title($label) : $label }}</option>
    @endforeach
</select>
