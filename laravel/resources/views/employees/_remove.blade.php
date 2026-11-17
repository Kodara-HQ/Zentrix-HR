<form method="post" action="{{ $route }}" onsubmit="return confirm('Remove this item?')">
    @csrf
    @method('delete')
    <button class="rounded p-1 text-muted hover:bg-sky hover:text-[#9f2d2d]" title="Remove"><x-lucide-trash-2 class="h-4 w-4" /></button>
</form>
