@props(['color' => 'slate'])

@php
$colors = [
    'slate' => 'bg-slate-100 text-slate-700',
    'green' => 'bg-green-100 text-green-800',
    'amber' => 'bg-amber-100 text-amber-800',
    'red' => 'bg-red-100 text-red-800',
    'blue' => 'bg-blue-100 text-blue-800',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 rounded-full text-xs font-medium '.($colors[$color] ?? $colors['slate'])]) }}>
    {{ $slot }}
</span>
