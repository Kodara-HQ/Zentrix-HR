<div class="flex gap-1.5">
    <form method="post" action="{{ $route }}">
        @csrf
        <input type="hidden" name="decision" value="APPROVED">
        <x-button type="submit" small>Approve</x-button>
    </form>
    <form method="post" action="{{ $route }}" onsubmit="const note = prompt('Reason for rejecting'); if (note === null) return false; this.note.value = note;">
        @csrf
        <input type="hidden" name="decision" value="REJECTED">
        <input type="hidden" name="note">
        <x-button type="submit" small tone="danger">Reject</x-button>
    </form>
</div>
