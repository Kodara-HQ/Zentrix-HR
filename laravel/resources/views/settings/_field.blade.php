{{-- One input for a SettingsLists field. $name, $field, $value, $own (whether old() input belongs to this form) --}}
@php
    [$label, $type] = $field;
    $required = str_contains($field[2] ?? '', 'required');
    $current = $own ? old($name, $value) : $value;
    if ($current instanceof \BackedEnum) { $current = $current->value; }
    if ($current instanceof \DateTimeInterface) { $current = $current->format('Y-m-d'); }
@endphp
@if ($type === 'bool')
    <label class="flex items-center gap-2 self-end pb-2 text-sm">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($own && old('_form') ? old($name) : ($value ?? in_array($name, ['affects_balance', 'paid', 'taxable'], true)))>
        {{ $label }}
    </label>
@elseif ($type === 'days')
    <x-field :label="$label" class="sm:col-span-2">
        <div class="flex flex-wrap gap-3 pt-1">
            @php($chosen = array_map('intval', (array) ($current ?? [1, 2, 3, 4, 5])))
            @foreach (\App\Support\SettingsLists::DAYS as $day => $short)
                <label class="flex items-center gap-1.5 text-sm"><input type="checkbox" name="{{ $name }}[]" value="{{ $day }}" @checked(in_array($day, $chosen, true))> {{ $short }}</label>
            @endforeach
        </div>
    </x-field>
@elseif ($type === 'select')
    <x-field :label="$label"><x-select :name="$name" :options="($field[3])()" :selected="$current" :placeholder="$required ? null : '—'" /></x-field>
@else
    <x-field :label="$label"><input type="{{ $type }}" name="{{ $name }}" value="{{ $current }}" @if ($type === 'number') step="any" @endif @required($required) class="{{ \App\Support\Ui::CONTROL }}"></x-field>
@endif
