{{-- Company details for the new-company and edit dialogs. Old input only refills the dialog that was sent. --}}
@php($mine = old('_form') === ($company ? "edit:{$company->id}" : 'add'))
@php($value = fn ($field, $default = null) => $mine ? old($field) : ($company?->{$field} ?? $default))
<div class="grid gap-3 sm:grid-cols-2">
    <x-field label="Company name"><input name="name" value="{{ $value('name') }}" required class="{{ \App\Support\Ui::CONTROL }}"></x-field>
    <x-field label="Registered (legal) name"><input name="legal_name" value="{{ $value('legal_name') }}" required class="{{ \App\Support\Ui::CONTROL }}"></x-field>
    <x-field label="Email"><input type="email" name="email" value="{{ $value('email') }}" class="{{ \App\Support\Ui::CONTROL }}"></x-field>
    <x-field label="Phone"><input name="phone" value="{{ $value('phone') }}" class="{{ \App\Support\Ui::CONTROL }}"></x-field>
    <x-field label="City"><input name="city" value="{{ $value('city') }}" class="{{ \App\Support\Ui::CONTROL }}"></x-field>
    <x-field label="Country"><input name="country" value="{{ $value('country', 'Ghana') }}" required class="{{ \App\Support\Ui::CONTROL }}"></x-field>
    <x-field label="Currency code"><input name="currency" value="{{ $value('currency', 'GHS') }}" required maxlength="3" class="{{ \App\Support\Ui::CONTROL }} uppercase"></x-field>
</div>

{{-- Colours: pick a ready-made pair or set each colour; the preview shows the sidebar and a button. --}}
@php($primary = $value('theme_primary', \App\Support\Theme::DEFAULT_PRIMARY) ?: \App\Support\Theme::DEFAULT_PRIMARY)
@php($accent = $value('theme_accent', \App\Support\Theme::DEFAULT_ACCENT) ?: \App\Support\Theme::DEFAULT_ACCENT)
<div class="border-t border-line pt-4" x-data="{ primary: @js(strtolower($primary)), accent: @js(strtolower($accent)) }">
    <h3 class="font-semibold text-ink">Colours</h3>
    <p class="mb-3 text-xs text-muted">Everyone in this company sees these colours. Pick a ready-made pair or choose your own; both must be dark enough for white text.</p>
    <div class="mb-3 flex flex-wrap gap-2">
        @foreach (\App\Support\Theme::PRESETS as $label => [$p, $a])
            <button type="button" title="{{ $label }}" aria-label="{{ $label }} colours" @click="primary = '{{ $p }}'; accent = '{{ $a }}'"
                    class="flex overflow-hidden rounded-lg border-2"
                    :class="primary === '{{ $p }}' && accent === '{{ $a }}' ? 'border-ink' : 'border-line'">
                <span class="h-8 w-6" style="background: {{ $p }}"></span><span class="h-8 w-6" style="background: {{ $a }}"></span>
            </button>
        @endforeach
    </div>
    <div class="grid items-end gap-3 sm:grid-cols-3">
        <x-field label="Main colour (sidebar)">
            <span class="flex items-center gap-2"><input type="color" name="theme_primary" x-model="primary" class="h-9 w-12 cursor-pointer rounded border border-line bg-white p-0.5"><span class="font-mono text-xs text-muted" x-text="primary"></span></span>
        </x-field>
        <x-field label="Accent colour (buttons, links)">
            <span class="flex items-center gap-2"><input type="color" name="theme_accent" x-model="accent" class="h-9 w-12 cursor-pointer rounded border border-line bg-white p-0.5"><span class="font-mono text-xs text-muted" x-text="accent"></span></span>
        </x-field>
        <div class="flex h-14 overflow-hidden rounded-lg border border-line" aria-hidden="true">
            <div class="flex w-1/3 flex-col gap-1 p-2" :style="`background: ${primary}`">
                <span class="h-1.5 w-full rounded bg-white/40"></span>
                <span class="h-1.5 w-full rounded" :style="`background: ${accent}`"></span>
                <span class="h-1.5 w-2/3 rounded bg-white/40"></span>
            </div>
            <div class="flex flex-1 items-center justify-center bg-cream">
                <span class="rounded-md px-2 py-1 text-xs font-semibold text-white" :style="`background: ${accent}`">Button</span>
            </div>
        </div>
    </div>
</div>
