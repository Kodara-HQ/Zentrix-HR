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
