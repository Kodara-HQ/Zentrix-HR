@php
    use App\Support\Lookups;
    $e = $employee ?? null;
    $creating = $e === null;
    $required = $creating ? 'required' : '';
@endphp
<div class="grid gap-3 sm:grid-cols-2">
    @if ($full)
        <x-field label="First name"><x-input name="first_name" :value="$e?->first_name" :required="$creating" /></x-field>
        <x-field label="Middle name"><x-input name="middle_name" :value="$e?->middle_name" /></x-field>
        <x-field label="Last name"><x-input name="last_name" :value="$e?->last_name" :required="$creating" /></x-field>
        <x-field label="Gender"><x-select name="gender" :options="Lookups::GENDERS" :labels="true" :selected="$e?->gender?->value ?? 'FEMALE'" /></x-field>
        <x-field label="Date of birth"><x-input type="date" name="date_of_birth" :value="$e?->date_of_birth" :required="$creating" /></x-field>
        <x-field label="Email"><x-input type="email" name="email" :value="$e?->email" :required="$creating" /></x-field>
    @endif
    <x-field label="Phone"><x-input name="phone" :value="$e?->phone" :required="$creating" /></x-field>
    <x-field label="Other phone"><x-input name="alt_phone" :value="$e?->alt_phone" /></x-field>
    <x-field label="Address" class="sm:col-span-2"><x-input name="address" :value="$e?->address" /></x-field>
    <x-field label="City"><x-input name="city" :value="$e?->city ?? ($creating ? 'Accra' : null)" /></x-field>
    <x-field label="Region"><x-input name="region" :value="$e?->region ?? ($creating ? 'Greater Accra' : null)" /></x-field>
    <x-field label="Emergency contact"><x-input name="emergency_name" :value="$e?->emergency_name" /></x-field>
    <x-field label="Emergency phone"><x-input name="emergency_phone" :value="$e?->emergency_phone" /></x-field>
    <x-field label="Emergency relationship"><x-input name="emergency_relation" :value="$e?->emergency_relation ?? ($creating ? 'Spouse' : null)" /></x-field>
    <x-field label="Next of kin"><x-input name="next_of_kin_name" :value="$e?->next_of_kin_name" /></x-field>
    <x-field label="Next of kin phone"><x-input name="next_of_kin_phone" :value="$e?->next_of_kin_phone" /></x-field>
    <x-field label="Next of kin relationship"><x-input name="next_of_kin_relation" :value="$e?->next_of_kin_relation ?? ($creating ? 'Parent' : null)" /></x-field>
    @if ($full)
        <x-field label="Department"><x-select name="department_id" :options="Lookups::departments()" :selected="$e?->department_id" placeholder="Select" :required="$creating" /></x-field>
        <x-field label="Job title"><x-select name="job_title_id" :options="Lookups::jobTitles()" :selected="$e?->job_title_id" placeholder="Select" :required="$creating" /></x-field>
        <x-field label="Employment type"><x-select name="employment_type_id" :options="Lookups::employmentTypes()" :selected="$e?->employment_type_id" placeholder="Select" :required="$creating" /></x-field>
        <x-field label="Location">
            @php($locations = Lookups::locations())
            <x-select name="location_id" :options="$locations" :selected="$e?->location_id" placeholder="Select" :required="$creating" />
            @if (! $locations)<p class="mt-1 text-xs text-[#9f2d2d]">Add a work location under System settings before saving an employee.</p>@endif
        </x-field>
        <x-field label="Reporting manager"><x-select name="manager_id" :options="array_diff_key(Lookups::managers(), [$e?->id => true])" :selected="$e?->manager_id" placeholder="None" /></x-field>
        <x-field label="Employment date"><x-input type="date" name="date_of_employment" :value="$e?->date_of_employment" :required="$creating" /></x-field>
        <x-field label="Probation ends"><x-input type="date" name="probation_end_date" :value="$e?->probation_end_date" /></x-field>
        <x-field label="Basic salary (GHS)"><x-input type="number" step="0.01" min="0" name="basic_salary" :value="$e?->basic_salary" :required="$creating" /></x-field>
        <x-field label="SSNIT number"><x-input name="ssnit_number" :value="$e?->ssnit_number" /></x-field>
        <x-field label="TIN"><x-input name="tin_number" :value="$e?->tin_number" /></x-field>
        @if ($creating)
            <x-field label="Contract end"><x-input type="date" name="contract_end_date" /></x-field>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input type="hidden" name="create_login" value="0">
                <input type="checkbox" name="create_login" value="1" @checked(old('create_login', '1') === '1')>
                Create a sign-in and show the temporary password once
            </label>
        @endif
    @endif
</div>
