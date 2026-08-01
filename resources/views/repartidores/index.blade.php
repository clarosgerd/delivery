@extends('layouts.app')

@section('title', 'Repartidores')

@section('content')
<x-card title="Agregar repartidor">
    <form method="POST" action="{{ route('repartidores.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Nombre</label>
            <input type="text" name="nombre" required class="w-full border border-slate-300 rounded-md px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Teléfono</label>
            <input type="text" name="telefono" class="w-full border border-slate-300 rounded-md px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Vehículo</label>
            <select name="tipo_vehiculo" class="border border-slate-300 rounded-md px-3 py-2">
                <option value="moto">Moto</option>
                <option value="auto">Auto</option>
                <option value="bicicleta">Bicicleta</option>
            </select>
        </div>
        <x-btn>Guardar</x-btn>
    </form>
</x-card>

<x-card title="Repartidores">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
            <tr class="bg-slate-100 text-left">
                <th class="px-3 py-2 font-semibold">Nombre</th>
                <th class="px-3 py-2 font-semibold">Teléfono</th>
                <th class="px-3 py-2 font-semibold">Vehículo</th>
                <th class="px-3 py-2 font-semibold">Última ubicación</th>
                <th class="px-3 py-2 font-semibold">Link para compartir</th>
                <th class="px-3 py-2"></th>
            </tr>
            </thead>
            <tbody>
            @forelse($repartidores as $r)
                <tr class="border-b border-slate-100">
                    <td class="px-3 py-2">{{ $r->nombre }}</td>
                    <td class="px-3 py-2">{{ $r->telefono ?? '—' }}</td>
                    <td class="px-3 py-2"><x-badge color="blue">{{ $r->tipo_vehiculo }}</x-badge></td>
                    <td class="px-3 py-2">{{ $r->ubicacion_at?->diffForHumans() ?? 'sin datos' }}</td>
                    <td class="px-3 py-2">
                        <input type="text" readonly value="{{ route('repartidor.show', $r->access_token) }}"
                            class="w-64 border border-slate-300 rounded-md px-2 py-1 text-xs" onclick="this.select()">
                    </td>
                    <td class="px-3 py-2">
                        @if($r->activo)
                            <form method="POST" action="{{ route('repartidores.destroy', $r) }}" onsubmit="return confirm('¿Desactivar a {{ $r->nombre }}?')">
                                @csrf
                                <x-btn type="submit" variant="danger">Desactivar</x-btn>
                            </form>
                        @else
                            <x-badge>inactivo</x-badge>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-4 text-slate-500">Sin repartidores todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
