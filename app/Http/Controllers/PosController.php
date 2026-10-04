<?php

namespace App\Http\Controllers;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use App\Services\RetiroSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            'pendientesApi' => $this->pendientesApi($evento),
        ]);
    }

    /**
     * Reenvía a ApiRestEvent el número/chip que delivery tiene y la API no
     * (aviso del POS, 04/10/2026). Solo toca los casos "pendiente_en_api"
     * del último sync; los de "distinto" no se pisan solos.
     */
    public function reenviarNumeracion(EventoRetiroConfig $evento)
    {
        $pendientes = $this->pendientesApi($evento);
        $documentos = collect($pendientes)->pluck('documento')->all();

        $ok = 0;
        $fallidos = 0;
        RetiroSitio::where('evento_id', $evento->evento_id)
            ->whereIn('documento', $documentos)
            ->get()
            ->each(function (RetiroSitio $retiro) use (&$ok, &$fallidos) {
                $retiro->reenviarNumeracionAApi() ? $ok++ : $fallidos++;
            });

        $mensaje = "Reenvío: {$ok} enviado(s)".($fallidos ? ", {$fallidos} con error" : '').'.';

        return redirect()->route('pos.show', $evento->evento_id)->with('status', $mensaje);
    }

    /**
     * Casos del último sync donde delivery tiene número/chip y la API no lo
     * tiene (ver RetiroSyncService::detectarDescuadre).
     */
    private function pendientesApi(EventoRetiroConfig $evento): array
    {
        $casos = Cache::get(RetiroSyncService::claveDescuadre($evento->evento_id), []);

        return array_values(array_filter($casos, fn (array $caso) => collect($caso['problemas'])
            ->contains(fn (string $p) => in_array($p, ['numero_pendiente_en_api', 'chip_pendiente_en_api'], true))));
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

    /**
     * Editar datos del participante al momento de la entrega (28/09/2026) —
     * nombre/apellido/genero/fecha_nacimiento libres; categoria_id solo si
     * ApiRestEvent confirma que el precio no cambia (si no, responde 422 con
     * "debe pasar por Caja", que se muestra tal cual en el popup). Disponible
     * tanto en pendiente como en entregado — corregir un dato después de la
     * entrega es un caso real (el staff lo nota recién al imprimir un
     * gafete).
     */
    public function editarDatos(Request $request, EventoRetiroConfig $evento, RetiroSitio $retiro)
    {
        abort_if($retiro->evento_id !== $evento->evento_id, 404);

        $data = $request->validate([
            'nombre' => ['sometimes', 'string', 'max:255'],
            'apellido' => ['sometimes', 'string', 'max:255'],
            'genero' => ['sometimes', 'string', 'max:50'],
            'fecha_nacimiento' => ['sometimes', 'date'],
            'categoria_id' => ['sometimes', 'integer'],
        ]);

        if (! $data) {
            return response()->json(['success' => false, 'error' => 'No hay ningún cambio para guardar.'], 422);
        }

        $resultado = $retiro->editarDatos($data);

        if (! $resultado['success']) {
            return response()->json(['success' => false, 'error' => $resultado['error']], 422);
        }

        // El push-back solo devuelve categoriaId (ApiRestEvent no resuelve
        // nombres) — se completa acá con el catálogo ya sincronizado, para
        // que la tarjeta no quede con el nombre viejo hasta el próximo sync.
        if (array_key_exists('categoria_id', $data)) {
            $nombre = collect($evento->categorias_catalogo ?? [])
                ->flatten(1)
                ->firstWhere('id', $retiro->fresh()->categoria_id)['name'] ?? null;
            if ($nombre) {
                $retiro->update(['categoria' => $nombre]);
            }
        }

        return response()->json(['success' => true, 'retiro' => $retiro->fresh()]);
    }
}
