@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-[#e7c7c7] bg-[#fdf6f6] px-3 py-2 text-sm text-[#9f2d2d]']) }}>
        @foreach (array_unique($errors->all()) as $message)<div>{{ $message }}</div>@endforeach
    </div>
@endif
