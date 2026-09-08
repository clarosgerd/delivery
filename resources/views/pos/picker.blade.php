@extends('layouts.app')

@section('title', 'POS — Elegir evento')

@section('content')
{{-- Picker de evento (07/09/2026) — antes /pos era directamente la pantalla
     de búsqueda con un <select> para elegir el evento, sin rastro del
     evento en la URL. Ahora cada evento tiene su propio link a
     /pos/{evento}, pensado para dejar el link correcto guardado/bookmarkeado
     en cada tablet/terminal, sin depender de que el staff elija bien de un
     combo cada vez. --}}
<x-card title="Elegí el evento">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
            <tr class="bg-slate-100 text-left">
                <th class="px-3 py-2 font-semibold">Evento</th>
                <th class="px-3 py-2 font-semibold">Nombre</th>
                <th class="px-3 py-2 font-semibold">Última sincronización</th>
                <th class="px-3 py-2"></th>
            </tr>
            </thead>
            <tbody>
            @forelse($eventos as $evento)
                <tr class="border-b border-slate-100">
                    <td class="px-3 py-2">{{ $evento->evento_id }}</td>
                    <td class="px-3 py-2">{{ $evento->evento_nombre ?? 'sin nombre' }}</td>
                    <td class="px-3 py-2">{{ $evento->last_synced_at?->format('Y-m-d H:i') ?? 'nunca' }}</td>
                    <td class="px-3 py-2">
                        <a href="{{ route('pos.show', $evento) }}" class="text-lg font-semibold text-brand-600 hover:underline">Abrir POS →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-4 text-slate-500">Sin eventos configurados todavía — cargalos desde <a href="{{ route('retiro.index') }}" class="text-brand-600 hover:underline">Retiro en sitio</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
