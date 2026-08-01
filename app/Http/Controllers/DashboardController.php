<?php

namespace App\Http\Controllers;

use App\Models\EnvioDelivery;
use App\Models\EventoDeliveryConfig;
use App\Models\Repartidor;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $eventoId = $request->integer('evento_id') ?: null;
        $estado = $request->string('estado')->toString() ?: null;

        $envios = EnvioDelivery::query()
            ->with('repartidor')
            ->when($eventoId, fn ($q) => $q->where('evento_id', $eventoId))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('evento_id')
            ->orderBy('nombre')
            ->paginate(50)
            ->withQueryString();

        return view('dashboard.index', [
            'envios' => $envios,
            'eventos' => EventoDeliveryConfig::orderBy('evento_id')->get(),
            'estados' => EnvioDelivery::ESTADOS,
            'repartidores' => Repartidor::where('activo', true)->orderBy('nombre')->get(),
            'eventoId' => $eventoId,
            'estado' => $estado,
        ]);
    }

    public function actualizarEstado(Request $request, EnvioDelivery $envio)
    {
        $data = $request->validate([
            'estado' => ['required', 'in:'.implode(',', EnvioDelivery::ESTADOS)],
        ]);

        $envio->avanzarEstado($data['estado']);

        return back()->with('status', "Envío #{$envio->id} actualizado a {$data['estado']}.");
    }

    public function asignarRepartidor(Request $request, EnvioDelivery $envio)
    {
        $data = $request->validate([
            'repartidor_id' => ['nullable', 'exists:repartidores,id'],
        ]);

        $envio->update(['repartidor_id' => $data['repartidor_id'] ?? null]);

        return back()->with('status', "Envío #{$envio->id} reasignado.");
    }
}
