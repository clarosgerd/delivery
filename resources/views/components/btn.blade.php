@props(['variant' => 'primary', 'type' => 'submit'])

@php
$variants = [
    'primary' => 'bg-brand-600 hover:bg-brand-700 text-white',
    'secondary' => 'bg-slate-200 hover:bg-slate-300 text-slate-800',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white',
];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'px-3 py-1.5 rounded-md text-sm font-medium transition-colors '.($variants[$variant] ?? $variants['primary'])]) }}>
    {{ $slot }}
</button>
