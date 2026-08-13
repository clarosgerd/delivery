@extends('layouts.app')

@section('title', 'Mis entregas — '.$repartidor->nombre)

@section('content')
<div class="max-w-lg mx-auto">
    <x-card>
        <h2 class="text-xl font-semibold mb-1">Hola, {{ $repartidor->nombre }}</h2>
        <p class="mb-1">Vehículo: <x-badge color="blue">{{ $repartidor->tipo_vehiculo }}</x-badge></p>
        <p id="ubicacion-status" class="text-sm text-slate-600">Activando ubicación…</p>
    </x-card>

    <x-card title="Tus entregas pendientes">
        <div class="space-y-3">
            @forelse($envios as $envio)
                <div class="border border-slate-200 rounded-lg p-3 flex justify-between items-center gap-3">
                    <div>
                        <div class="font-semibold">{{ $envio->nombre ?? '—' }}</div>
                        <div class="text-sm text-slate-600">
                            {{ $envio->direccion ?? '—' }} · {{ $envio->telefono ?? '—' }}
                            @if ($envio->lat !== null && $envio->lng !== null)
                                · <a href="https://www.google.com/maps?q={{ $envio->lat }},{{ $envio->lng }}" target="_blank" rel="noopener" class="text-blue-600 underline">Abrir en Maps →</a>
                            @endif
                        </div>
                        <div class="text-sm text-slate-600">Kit: {{ $envio->kit ?? '—' }}</div>
                        <x-badge color="amber" class="mt-1">{{ $envio->estado }}</x-badge>
                    </div>
                    <form method="POST" action="{{ route('repartidor.entregar', [$repartidor->access_token, $envio]) }}" onsubmit="return confirm('¿Marcar como entregado?')">
                        @csrf
                        <x-btn>Entregado</x-btn>
                    </form>
                </div>
            @empty
                <p class="text-slate-500">No tenés entregas pendientes asignadas.</p>
            @endforelse
        </div>
    </x-card>
</div>

<script>
const token = @json($repartidor->access_token);
const urlUbicacion = @json(route('repartidor.ubicacion', $repartidor->access_token));
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const statusEl = document.getElementById('ubicacion-status');

function enviarUbicacion(pos) {
    fetch(urlUbicacion, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
    }).then(() => {
        statusEl.textContent = 'Ubicación compartida — ' + new Date().toLocaleTimeString();
    }).catch(() => {
        statusEl.textContent = 'No se pudo enviar la ubicación (sin conexión?).';
    });
}

if (!navigator.geolocation) {
    statusEl.textContent = 'Este navegador no soporta geolocalización.';
} else {
    navigator.geolocation.watchPosition(enviarUbicacion, (err) => {
        statusEl.textContent = 'No pudimos acceder a tu ubicación: ' + err.message + '. Activala para que te puedan seguir en el mapa.';
    }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 });
}
</script>
@endsection
