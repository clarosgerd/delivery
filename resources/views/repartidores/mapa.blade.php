@extends('layouts.app')

@section('title', 'Mapa en vivo')

@section('content')
<x-card title="Repartidores en vivo">
    <p class="text-sm text-slate-600 mb-3">Se actualiza solo cada 10 segundos. Requiere que el repartidor haya abierto su link y aceptado compartir ubicación.</p>
    <div id="mapa" class="h-[500px] rounded-md"></div>
</x-card>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('mapa').setView([-16.5, -68.15], 12); // La Paz, Bolivia como default razonable
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
}).addTo(map);

const markers = {};
const iconos = { moto: '🏍️', auto: '🚗', bicicleta: '🚲' };

async function actualizar() {
    const res = await fetch('{{ route('repartidores.ubicaciones') }}');
    const repartidores = await res.json();

    const vistos = new Set();
    repartidores.forEach(r => {
        vistos.add(r.id);
        const emoji = iconos[r.tipo_vehiculo] || '📍';
        const popup = `${emoji} <b>${r.nombre}</b><br>Actualizado: ${new Date(r.ubicacion_at).toLocaleTimeString()}`;

        if (markers[r.id]) {
            markers[r.id].setLatLng([r.lat, r.lng]).setPopupContent(popup);
        } else {
            markers[r.id] = L.marker([r.lat, r.lng]).addTo(map).bindPopup(popup);
        }
    });

    Object.keys(markers).forEach(id => {
        if (!vistos.has(Number(id))) {
            map.removeLayer(markers[id]);
            delete markers[id];
        }
    });

    if (repartidores.length && Object.keys(markers).length === repartidores.length) {
        const grupo = L.featureGroup(Object.values(markers));
        if (repartidores.length > 1) map.fitBounds(grupo.getBounds().pad(0.2));
    }
}

actualizar();
setInterval(actualizar, 10000);
</script>
@endsection
