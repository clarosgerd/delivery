@extends('layouts.app')

@section('title', 'Entregas en sitio — '.($evento->evento_nombre ?? 'Evento '.$evento->evento_id))

@section('content')
<div class="max-w-4xl mx-auto">
    <h1 class="text-xl font-bold mb-1">{{ $evento->evento_nombre ?? 'Evento '.$evento->evento_id }}</h1>
    <p class="text-sm text-slate-500 mb-5">Reporte de entregas en sitio (retiro en el evento) — actualizado en tiempo real.</p>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <x-card class="!mb-0 text-center">
            <div class="text-2xl font-bold">{{ $resumen['total'] }}</div>
            <div class="text-xs text-slate-500">Total</div>
        </x-card>
        <x-card class="!mb-0 text-center">
            <div class="text-2xl font-bold text-green-700">{{ $resumen['entregados'] }}</div>
            <div class="text-xs text-slate-500">Entregados</div>
        </x-card>
        <x-card class="!mb-0 text-center">
            <div class="text-2xl font-bold text-amber-700">{{ $resumen['pendientes'] }}</div>
            <div class="text-xs text-slate-500">Pendientes</div>
        </x-card>
        <x-card class="!mb-0 text-center">
            <div class="text-2xl font-bold">{{ $resumen['porcentaje'] }}%</div>
            <div class="text-xs text-slate-500">Entregado</div>
        </x-card>
    </div>

    <div class="flex gap-2 mb-3 text-sm">
        <a href="{{ request()->fullUrlWithQuery(['estado' => null]) }}"
           class="px-3 py-1.5 rounded-md {{ $estadoSeleccionado === '' ? 'bg-brand-600 text-white' : 'bg-white border border-slate-300' }}">Todos</a>
        <a href="{{ request()->fullUrlWithQuery(['estado' => 'pendiente']) }}"
           class="px-3 py-1.5 rounded-md {{ $estadoSeleccionado === 'pendiente' ? 'bg-brand-600 text-white' : 'bg-white border border-slate-300' }}">Pendientes</a>
        <a href="{{ request()->fullUrlWithQuery(['estado' => 'entregado']) }}"
           class="px-3 py-1.5 rounded-md {{ $estadoSeleccionado === 'entregado' ? 'bg-brand-600 text-white' : 'bg-white border border-slate-300' }}">Entregados</a>
        <a href="{{ $csvUrl }}"
           class="ml-auto px-3 py-1.5 rounded-md bg-white border border-slate-300 hover:bg-slate-50">Descargar CSV</a>
    </div>

    <x-card class="!p-0 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="px-3 py-2">Nombre</th>
                    <th class="px-3 py-2">Documento</th>
                    <th class="px-3 py-2">Categoría</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Entregado por</th>
                    <th class="px-3 py-2">Entregado el</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($retiros as $r)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-2">{{ $r->nombre }} {{ $r->apellido }}</td>
                        <td class="px-3 py-2">{{ $r->documento }}</td>
                        <td class="px-3 py-2">{{ $r->categoria }}</td>
                        <td class="px-3 py-2">
                            @if ($r->estado === 'entregado')
                                <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-xs font-medium">Entregado</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-medium">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $r->entregado_por ?? '—' }}</td>
                        <td class="px-3 py-2">{{ $r->entregado_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-3 py-6 text-center text-slate-400">Sin participantes para este filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>
@endsection
