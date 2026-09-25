<?php

namespace App\Http\Controllers;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use Illuminate\Http\Request;

class PosController extends Controller
{
    /**
     * Picker de evento (07/09/2026) — antes esta acción era la propia
     * pantalla de POS con un <select> para elegir el evento, sin rastro en
     * la URL (ver pos.show). Ahora es solo la lista de eventos configurados,
     * cada uno con su propio link a /pos/{evento}.
     *
     * Compat con la forma vieja `/pos?evento_id=X` (que existía como
     * pre-selección cosmética del <select>, y que retiro/index.blade.php
     * usaba para el link "Abrir POS") — redirige directo a la pantalla
     * scoped en vez de mostrar el picker.
     */
    public function index(Request $request)
    {
        if ($request->filled('evento_id')) {
            return redirect()->route('pos.show', $request->integer('evento_id'));
        }

        return view('pos.picker', [
            'eventos' => EventoRetiroConfig::orderBy('evento_id')->get(),
        ]);
    }

    /**
     * Pantalla de POS para UN evento específico — el evento ya viene
     * resuelto y confiable desde la URL (route-model-binding, 404
     * automático si `evento_id` no está configurado), no hay combo para
     * elegir mal.
     */
    public function show(EventoRetiroConfig $evento)
    {
        return view('pos.index', [
            'evento' => $evento,
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
    public function buscar(Request $request, EventoRetiroConfig $evento)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2'],
        ]);

        $q = $data['q'];

        // Buscar por categoría/curso (16/09/2026) — antes solo buscaba por
        // documento/nombre/apellido/referencia. Sin esto, el staff no podía
        // encontrar a nadie escribiendo el nombre de su categoría o curso
        // (ej. "URIANÁLISIS" en un congreso con cursos pre-congreso, o
        // "15K" en una carrera) — no es algo específico de congresos, es
        // un gap general del buscador.
        $resultados = RetiroSitio::where('evento_id', $evento->evento_id)
            ->where(function ($query) use ($q) {
                $query->where('documento', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('referencia', 'like', "%{$q}%")
                    ->orWhere('categoria', 'like', "%{$q}%")
                    ->orWhere('nombre_curso', 'like', "%{$q}%");
            })
            ->orderBy('apellido')
            ->limit(20)
            ->get();

        return response()->json($resultados);
    }

    public function entregar(Request $request, EventoRetiroConfig $evento, RetiroSitio $retiro)
    {
        // Protección real (07/09/2026) — sin esto, anidar `{evento}` en la
        // URL es solo cosmético: alguien podría seguir confirmando una
        // entrega de OTRO evento apuntando a un id de retiro que no le
        // corresponde. `{evento}` y `{retiro}` bindean de forma
        // independiente, Laravel no los cruza solo.
        abort_if($retiro->evento_id !== $evento->evento_id, 404);

        $data = $request->validate([
            'entregado_por' => ['nullable', 'string', 'max:255'],
            'numero_corredor' => ['nullable', 'string', 'max:50'],
            'chip' => ['nullable', 'string', 'max:50'],
        ]);

        // Control de duplicados (24/09/2026) — antes que cualquier efecto
        // secundario (cobro, asignación, marcar entregado): no confirmar la
        // entrega si el número/chip que se está por asignar ya fue
        // entregado a OTRO participante de este evento. Ver
        // RetiroSitio::conflictoEntregado() — acotado a solo contra
        // `entregado`, un duplicado entre 2 pendientes no bloquea.
        $conflicto = RetiroSitio::conflictoEntregado(
            $evento->evento_id, $retiro->id, $data['numero_corredor'] ?? null, $data['chip'] ?? null
        );
        if ($conflicto) {
            $campo = filled($data['numero_corredor'] ?? null) && $conflicto->numero_corredor === $data['numero_corredor']
                ? 'número'
                : 'chip';
            return response()->json([
                'success' => false,
                'error' => "Ese {$campo} ya fue entregado a {$conflicto->nombre} {$conflicto->apellido} (doc. {$conflicto->documento}) — no se entregó el kit.",
            ], 422);
        }

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

    public function deshacer(EventoRetiroConfig $evento, RetiroSitio $retiro)
    {
        abort_if($retiro->evento_id !== $evento->evento_id, 404);

        $retiro->deshacerEntrega();

        return response()->json(['success' => true, 'retiro' => $retiro->fresh()]);
    }
}
