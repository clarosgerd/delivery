@extends('layouts.app')

@section('title', 'Dashboard de delivery')

@php
$colorEstado = ['pendiente' => 'amber', 'confirmado' => 'blue', 'entregado' => 'green', 'cancelado' => 'red'];
$selectClass = 'border border-slate-300 rounded-md px-2 py-1.5 text-sm';
@endphp

@section('content')
<x-card>
    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium mb-1">Evento</label>
            <select name="evento_id" class="{{ $selectClass }}">
                <option value="">Todos</option>
                @foreach($eventos as $evento)
                    <option value="{{ $evento->evento_id }}" @selected($eventoId == $evento->evento_id)>
                        {{ $evento->evento_id }} — {{ $evento->evento_nombre ?? 'sin nombre' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Estado</label>
            <select name="estado" class="{{ $selectClass }}">
                <option value="">Todos</option>
                @foreach($estados as $e)
                    <option value="{{ $e }}" @selected($estado === $e)>{{ $e }}</option>
                @endforeach
            </select>
        </div>
        <x-btn type="submit" variant="secondary">Filtrar</x-btn>
    </form>
</x-card>

<x-card>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
            <tr class="bg-slate-100 text-left">
                <th class="px-3 py-2 font-semibold">Evento</th>
                <th class="px-3 py-2 font-semibold">Participante</th>
                <th class="px-3 py-2 font-semibold">Nombre</th>
                <th class="px-3 py-2 font-semibold">Dirección</th>
                <th class="px-3 py-2 font-semibold">Teléfono</th>
                <th class="px-3 py-2 font-semibold">Kit</th>
                <th class="px-3 py-2 font-semibold">Repartidor</th>
                <th class="px-3 py-2 font-semibold">Estado</th>
                <th class="px-3 py-2 font-semibold">Cambiar</th>
            </tr>
            </thead>
            <tbody>
            @forelse($envios as $envio)
                <tr class="border-b border-slate-100">
                    <td class="px-3 py-2">{{ $envio->evento_id }}</td>
                    <td class="px-3 py-2">{{ $envio->participante_id }}</td>
                    <td class="px-3 py-2">{{ $envio->nombre ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $envio->direccion ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $envio->telefono ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $envio->kit ?? '—' }}</td>
                    <td class="px-3 py-2">
                        <form method="POST" action="{{ route('envios.repartidor', $envio) }}">
                            @csrf
                            <select name="repartidor_id" onchange="this.form.submit()" class="{{ $selectClass }}">
                                <option value="">Sin asignar</option>
                                @foreach($repartidores as $r)
                                    <option value="{{ $r->id }}" @selected($envio->repartidor_id === $r->id)>{{ $r->nombre }} ({{ $r->tipo_vehiculo }})</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td class="px-3 py-2"><x-badge :color="$colorEstado[$envio->estado] ?? 'slate'">{{ $envio->estado }}</x-badge></td>
                    <td class="px-3 py-2">
                        <form method="POST" action="{{ route('envios.estado', $envio) }}">
                            @csrf
                            <select name="estado" onchange="this.form.submit()" class="{{ $selectClass }}">
                                @foreach($estados as $e)
                                    <option value="{{ $e }}" @selected($envio->estado === $e)>{{ $e }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-3 py-4 text-slate-500">No hay envíos importados todavía. Andá a "Importar eventos" para sincronizar uno.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $envios->links() }}</div>
</x-card>
@endsection
