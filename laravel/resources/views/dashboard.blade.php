<x-layouts.app title="Dashboard">
    <x-page-header :title="'Welcome, '.$user->first_name" :subtitle="\App\Support\Format::role($user->role)" />
    <div class="rounded-2xl border border-dashed border-line bg-paper p-8 text-sm text-muted">
        The dashboard figures are still being moved over from the previous version of the system.
    </div>
</x-layouts.app>
