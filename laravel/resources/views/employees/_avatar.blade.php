@if ($employee->photo_file_id)
    <img src="{{ route('files.show', $employee->photo_file_id) }}" alt="" class="h-20 w-20 rounded-full border border-line object-cover">
@else
    <div class="grid h-20 w-20 place-items-center rounded-full bg-navy text-xl font-semibold text-white">{{ $initials }}</div>
@endif
