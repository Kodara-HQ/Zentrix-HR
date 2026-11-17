<x-layouts.app title="Dashboard">
    <x-page-header :title="'Welcome, '.$user->first_name" :subtitle="\App\Support\Format::role($user->role)" />
    <div class="rounded-2xl border border-dashed border-[#e0d6c6] bg-[#fffcf8] p-8 text-sm text-[#6e675e]">
        The dashboard figures are still being moved over from the previous version of the system.
    </div>
</x-layouts.app>
