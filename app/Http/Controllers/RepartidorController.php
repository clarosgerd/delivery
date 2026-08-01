<?php

namespace App\Http\Controllers;

use App\Models\EnvioDelivery;
use App\Models\Repartidor;
use Illuminate\Http\Request;

class RepartidorController extends Controller
{
    /**
     * Acceso del repartidor: link con token opaco, sin login — mismo
     * criterio que los dashboards sin login que ya existen en ApiRestEvent
     * (signed routes), pero acá es un token propio porque este servicio no
     * comparte APP_KEY con ApiRestEvent.
     */
    public function show(string $token)
    {
        $repartidor = Repartidor::where('access_token', $token)->where('activo', true)->firstOrFail();

        return view('repartidor.show', [
            'repartidor' => $repartidor,
            'envios' => $repartidor->envios()->whereNotIn('estado', ['entregado', 'cancelado'])->orderBy('nombre')->get(),
        ]);
    }

    public function ubicacion(Request $request, string $token)
    {
        $repartidor = Repartidor::where('access_token', $token)->where('activo', true)->firstOrFail();

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $repartidor->registrarUbicacion($data['lat'], $data['lng']);

        return response()->json(['success' => true]);
    }

    public function marcarEntregado(Request $request, string $token, EnvioDelivery $envio)
    {
        $repartidor = Repartidor::where('access_token', $token)->where('activo', true)->firstOrFail();

        abort_unless($envio->repartidor_id === $repartidor->id, 403);

        $envio->avanzarEstado('entregado');

        return back()->with('status', "Envío #{$envio->id} marcado como entregado.");
    }
}
