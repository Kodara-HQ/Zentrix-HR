@php
    $a = $account;
    $available = array_diff_key($employees, array_flip(array_diff($linked, [$a?->employee_id])));
@endphp
<div class="grid gap-3 sm:grid-cols-2">
    <x-field label="Full name"><x-input name="full_name" :value="$a ? trim($a->first_name.' '.($a->last_name !== $a->first_name ? $a->last_name : '')) : null" required /></x-field>
    <x-field label="Username"><x-input name="username" :value="$a?->username" required autocomplete="off" /></x-field>
    <x-field label="Email"><x-input type="email" name="email" :value="$a?->email" /></x-field>
    <x-field label="Role"><x-select name="role" :options="$roles" :selected="$a?->role?->value ?? 'EMPLOYEE'" required /></x-field>
    <x-field label="Linked employee" class="sm:col-span-2">
        <x-select name="employee_id" :options="$available" :selected="$a?->employee_id" placeholder="None" />
        <span class="mt-1 block text-xs text-muted">Needed for self-service, and for managers and supervisors so they see their own department or team.</span>
    </x-field>
</div>
