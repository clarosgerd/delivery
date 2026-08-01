@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-white border border-slate-200 rounded-lg p-4 mb-5 shadow-sm']) }}>
    @if($title)
        <h2 class="text-lg font-semibold mb-3">{{ $title }}</h2>
    @endif
    {{ $slot }}
</div>
