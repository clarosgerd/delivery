<?php

namespace App\Http\Controllers;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index()
    {
        return view('pos.index', [
            'eventos' => EventoRetiroConfig::orderBy('evento_id')->get(),
        ]);
    }

    /**
     * Búsqueda en vivo para la pantalla POS. Sin id de participante (ver
     * RetiroConfigController), así que se busca por documento, nombre o
     * referencia — cualquiera que el participante pueda decir en el
     * mostrador. La referencia es de la inscripción completa, así que puede
     * traer a varios integrantes de un mismo grupo familiar/equipo de una
     * sola búsqueda.
     */
    public function buscar(Request $request)
    {
        $data = $request->validate([
            'evento_id' => ['required', 'integer'],
            'q' => ['required', 'string', 'min:2'],
        ]);

        $q = $data['q'];

        $resultados = RetiroSitio::where('evento_id', $data['evento_id'])
            ->where(function ($query) use ($q) {
                $query->where('documento', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%");
            })
            ->orderBy('apellido')
            ->limit(20)
            ->get();

        return response()->json($resultados);
    }

    public function entregar(Request $request, RetiroSitio $retiro)
    {
        $data = $request->validate([
            'entregado_por' => ['nullable', 'string', 'max:255'],
            'numero_corredor' => ['nullable', 'string', 'max:50'],
            'chip' => ['nullable', 'string', 'max:50'],
        ]);

        // Cobro en sitio (12/08/2026) — ver
        // ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
        // sección 0. Si esta fila sigue pendiente de un form_type sin
        // categoría, "Confirmar entrega" primero intenta cobrar/confirmar
        // el pago contra ApiRestEvent — si eso falla, **no se entrega el
        // kit**: no hay confirmación real de que el dinero quedó
        // registrado. El staff ve el error y puede reintentar (problema de
        // red momentáneo) sin haber soltado el kit.
        if ($retiro->pendienteDeCobroEnSitio()) {
            if (! $retiro->cobrarPagoSitio()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo confirmar el pago contra el sistema de inscripciones. Reintentá — no se entregó el kit.',
                ], 422);
            }
        }

        // Numeración cargada en el momento de la entrega (proveedor externo
        // no llegó a tiempo) — solo asigna lo que todavía esté vacío, ver
        // RetiroSitio::asignarNumeracion().
        $retiro->asignarNumeracion($data['numero_corredor'] ?? null, $data['chip'] ?? null);
        $retiro->marcarEntregado($data['entregado_por'] ?? null);

        return response()->json(['success' => true, 'retiro' => $retiro->fresh()]);
    }

    public function deshacer(RetiroSitio $retiro)
    {
        $retiro->deshacerEntrega();

        return response()->json(['success' => true, 'retiro' => $retiro->fresh()]);
    }
}
