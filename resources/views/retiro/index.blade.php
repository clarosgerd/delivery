@extends('layouts.app')

@section('title', 'Retiro en sitio — configurar evento')

@section('content')
<x-card title="Agregar / actualizar evento">
    <p class="text-sm text-slate-600 mb-4">
        Pegá la URL firmada del CSV de participantes que ya expone <code class="bg-slate-100 px-1 rounded">ApiRestEvent</code>
        (<code class="bg-slate-100 px-1 rounded">organizador.dashboard.export</code>, la misma que usa el organizador —
        generada con <code class="bg-slate-100 px-1 rounded">php artisan organizador:generar-link {evento}</code> o
        equivalente). Trae a <strong>todos</strong> los participantes pagados, no solo los de delivery a domicilio.
        Es un paso manual único por evento — una vez pegado, el sistema lo vuelve a sincronizar
        solo cada 5 minutos, útil sobre todo el día del evento para que el POS tenga los pagos
        recientes.
    </p>
    <form method="POST" action="{{ route('retiro.store') }}" class="space-y-3">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Evento ID</label>
            <input type="number" name="evento_id" required class="border border-slate-300 rounded-md px-3 py-2 w-40">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Nombre del evento (opcional)</label>
            <input type="text" name="evento_nombre" class="w-full border border-slate-300 rounded-md px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">URL del CSV firmado</label>
            <input type="url" name="csv_url" required class="w-full border border-slate-300 rounded-md px-3 py-2">
        </div>
        <x-btn>Guardar</x-btn>
    </form>
</x-card>

<x-card title="Eventos configurados">
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
                    <td class="px-3 py-2">{{ $evento->evento_nombre ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $evento->last_synced_at?->format('Y-m-d H:i') ?? 'nunca' }}</td>
                    <td class="px-3 py-2 flex items-center gap-3">
                        <form method="POST" action="{{ route('retiro.sync', $evento) }}">
                            @csrf
                            <x-btn type="submit" variant="secondary">Sincronizar ahora</x-btn>
                        </form>
                        <a class="text-brand-600 hover:underline" href="{{ route('pos.index', ['evento_id' => $evento->evento_id]) }}">Abrir POS</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-3 py-4 text-slate-500">Sin eventos configurados todavía.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>

@if (session('omitidos') && count(session('omitidos')))
    <x-card title="Filas omitidas (sin documento)">
        <pre class="whitespace-pre-wrap bg-slate-100 p-3 rounded-md text-xs overflow-x-auto">{{ json_encode(session('omitidos'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </x-card>
@endif
@endsection
