<?php

namespace App\Services;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lógica de sync del CSV de participantes, compartida entre el botón manual
 * ("Sincronizar ahora") y el comando programado
 * (`retiro:sincronizar-todos`, ver routes/console.php).
 */
class RetiroSyncService
{
    public function sincronizar(EventoRetiroConfig $config): array
    {
        $response = Http::timeout(15)->get($config->csv_url);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => "El CSV devolvió HTTP {$response->status()} para el evento {$config->evento_id}. Puede haber expirado o estar mal copiado.",
            ];
        }

        $filas = $this->parsearCsv($response->body());

        $actualizados = 0;
        $omitidos = [];
        $descuadres = [];

        foreach ($filas as $fila) {
            // Cobro en sitio (12/08/2026) — ver
            // ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
            // sección 0. Antes se descartaba cualquier fila que no fuera
            // 'paid'; ahora también entra una 'pending' si ApiRestEvent la
            // marcó elegible para cobro en sitio (ConfirmarPagoSitioUrl no
            // vacío — form_type sin categoría, ver
            // OrganizadorDashboardController::exportCsv). Cualquier otro
            // pendiente (con categoría, o QR en curso) se sigue
            // descartando: este flujo es acotado a propósito, no es "cobro
            // en efectivo genérico".
            $confirmarPagoSitioUrl = trim($fila['ConfirmarPagoSitioUrl'] ?? '');
            $esElegibleCobroSitio = $confirmarPagoSitioUrl !== '';
            if (($fila['Estado de pago'] ?? null) !== 'paid' && ! $esElegibleCobroSitio) {
                continue;
            }

            $documento = trim($fila['Documento'] ?? '');
            if ($documento === '') {
                $omitidos[] = $fila;
                continue;
            }

            // Una fila mala no debe tirar abajo el sync de TODO el evento
            // (29/09/2026) — bug real en UAT: antes de esto, un
            // QueryException al guardar UNA fila (ver el fix de
            // categoria_id en sincronizarFila()) se propagaba sin atajar,
            // así que `retiro:sincronizar-todos` abortaba entero (exit code
            // 1) sin procesar ni siquiera el resto de las filas de ESTE
            // evento, y el comando ni llegaba a intentar los eventos
            // siguientes en el foreach de SincronizarRetiro::handle(). Ahora
            // una fila que falle queda en $omitidos (con el motivo) y el
            // sync sigue con la próxima — mismo criterio de resiliencia que
            // se agregó en el comando (ver SincronizarRetiro::handle()).
            try {
                // Antes de sobrescribir: si delivery tiene número/chip y la
                // API no (o distinto), queda como descuadre para el aviso.
                if ($descuadre = $this->detectarDescuadre($config, $fila, $documento)) {
                    $descuadres[] = $descuadre;
                }
                $this->sincronizarFila($config, $fila, $documento);
                $actualizados++;
            } catch (\Throwable $e) {
                Log::warning('Sync de retiro en sitio: fila omitida por error', [
                    'evento_id' => $config->evento_id,
                    'documento' => $documento,
                    'error' => $e->getMessage(),
                ]);
                $omitidos[] = $fila;
            }
        }

        // Numeración/chip solo aplica a carreras, no a congresos
        // (16/09/2026) — es una propiedad del EVENTO completo, no de cada
        // fila, así que se toma de la primera fila del CSV (antes del
        // filtro de pago de arriba, para no depender de que al menos una
        // fila haya quedado 'paid'/elegible). Si el CSV no trae la
        // columna (ApiRestEvent viejo, sin este cambio todavía), no se
        // toca — el default `true` de la migración sigue rigiendo.
        if (isset($filas[0]['UsaNumeracion'])) {
            $config->usa_numeracion = filled($filas[0]['UsaNumeracion']);
        }

        // Catálogo de categorías para el popup de "Editar datos" (28/09/2026)
        // — solo va en la primera fila del CSV (igual que UsaNumeracion),
        // agrupado por nombre de tipo de formulario.
        if (filled($filas[0]['CatalogoCategorias'] ?? null)) {
            $decodificado = json_decode($filas[0]['CatalogoCategorias'], true);
            if (is_array($decodificado)) {
                $config->categorias_catalogo = $decodificado;
            }
        }

        Cache::put(self::claveDescuadre($config->evento_id), $descuadres, now()->addDays(2));

        $config->last_synced_at = now();
        $config->save();

        return ['ok' => true, 'actualizados' => $actualizados, 'omitidos' => $omitidos, 'descuadres' => count($descuadres)];
    }

    /**
     * Clave de caché donde el último sync deja los descuadres de numeración
     * de un evento. La lee el aviso diario y el POS (ver PosController).
     */
    public static function claveDescuadre(int $eventoId): string
    {
        return "numeracion_descuadre.{$eventoId}";
    }

    /**
     * Compara lo que tiene delivery contra el CSV de ApiRestEvent (lo que
     * luego va a ChronoTrack). Solo reporta cuando delivery tiene un dato y
     * la API no, o los dos tienen valores distintos. Si la API tiene dato y
     * delivery no, no es descuadre: el sync lo completa solo.
     *
     * @return array{documento: string, nombre: ?string, apellido: ?string, numero_delivery: string, numero_api: string, chip_delivery: string, chip_api: string, problemas: list<string>}|null
     */
    private function detectarDescuadre(EventoRetiroConfig $config, array $fila, string $documento): ?array
    {
        $local = RetiroSitio::where('evento_id', $config->evento_id)->where('documento', $documento)->first();
        if (! $local) {
            return null;
        }

        $numeroDelivery = trim((string) $local->numero_corredor);
        $numeroApi = trim((string) ($fila['NumeroCorredor'] ?? ''));
        $chipDelivery = trim((string) $local->chip);
        $chipApi = trim((string) ($fila['Chip'] ?? ''));

        $problemas = [];
        if ($numeroDelivery !== '' && $numeroApi === '') {
            $problemas[] = 'numero_pendiente_en_api';
        } elseif ($numeroDelivery !== '' && $numeroApi !== $numeroDelivery) {
            $problemas[] = 'numero_distinto';
        }
        if ($chipDelivery !== '' && $chipApi === '') {
            $problemas[] = 'chip_pendiente_en_api';
        } elseif ($chipDelivery !== '' && $chipApi !== $chipDelivery) {
            $problemas[] = 'chip_distinto';
        }

        if ($problemas === []) {
            return null;
        }

        return [
            'documento' => $documento,
            'nombre' => $local->nombre,
            'apellido' => $local->apellido,
            'numero_delivery' => $numeroDelivery,
            'numero_api' => $numeroApi,
            'chip_delivery' => $chipDelivery,
            'chip_api' => $chipApi,
            'problemas' => $problemas,
        ];
    }

    /**
     * Una sola fila del CSV → un `RetiroSitio`. Separado de sincronizar()
     * (29/09/2026) para poder envolverlo en try/catch por fila sin anidar
     * demasiado — ver el comentario en sincronizar().
     */
    private function sincronizarFila(EventoRetiroConfig $config, array $fila, string $documento): void
    {
        $confirmarPagoSitioUrl = trim($fila['ConfirmarPagoSitioUrl'] ?? '');
        $esElegibleCobroSitio = $confirmarPagoSitioUrl !== '';

        // Igual que en el import de delivery: el estado (entregado/no)
        // solo se toca localmente, un re-sync nunca lo pisa.
        $retiro = RetiroSitio::firstOrNew([
            'evento_id' => $config->evento_id,
            'documento' => $documento,
        ]);

        $monto = trim($fila['MontoPendiente'] ?? '');

        $retiro->fill([
            'nombre' => $fila['Nombre'] ?? null,
            'apellido' => $fila['Apellido'] ?? null,
            'categoria' => $fila['Categoría'] ?? null,
            'tipo_formulario' => $fila['Tipo de formulario'] ?? null,
            'talla' => $fila['Talla/Polera'] ?? null,
            'souvenirs' => $fila['Souvenirs'] ?? null,
            'telefono' => $fila['Teléfono'] ?? null,
            'correo' => $fila['Correo'] ?? null,
            // pago_status SÍ se pisa en cada re-sync (a diferencia de
            // `estado`, que es operativo de este servicio): la fuente
            // de verdad del pago es siempre ApiRestEvent, nunca algo
            // que se opere acá — si ya se cobró en sitio en un sync
            // previo, el próximo sync trae 'paid' desde el CSV real de
            // todos modos, así que no hay pisada real de nada operado
            // localmente.
            'pago_status' => $fila['Estado de pago'] ?? null,
            'monto' => $monto !== '' ? $monto : null,
            'confirmar_pago_sitio_url' => $esElegibleCobroSitio ? $confirmarPagoSitioUrl : null,
            'referencia' => $fila['Referencia'] ?? null,
            // Aviso de numeración vs. género/edad real en entrega de kit
            // (16/09/2026) — a diferencia de numero_corredor/chip, estas
            // 3 sí se pisan en cada re-sync: género/fecha de nacimiento
            // no se editan localmente, y la alerta es siempre recalculada
            // por ApiRestEvent contra el estado vigente de la numeración
            // (ver NumeracionRangoChecker) — no hay nada operado acá que
            // un sync viejo pueda pisar por error.
            'genero' => $fila['Género'] ?? null,
            'fecha_nacimiento' => filled($fila['FechaNacimiento'] ?? null) ? $fila['FechaNacimiento'] : null,
            // Edad usada para el aviso (16/09/2026) — no es la edad "de
            // hoy", es la que ApiRestEvent calculó según el método de
            // la categoría (ver CalculoEdadResolver) — mostrar esta en
            // vez de calcularla de nuevo acá evita que la tarjeta
            // muestre una edad distinta de la que realmente se usó
            // para decidir si avisar o no.
            'edad_calculada' => filled($fila['EdadCalculada'] ?? null) ? $fila['EdadCalculada'] : null,
            'alerta_numeracion' => filled($fila['AlertaNumeracion'] ?? null) ? $fila['AlertaNumeracion'] : null,
            // Fusión de inscripciones duplicadas por persona — curso
            // pre-congreso (16/09/2026) — vacías salvo que esta persona
            // también tenga una inscripción a un curso pre-congreso
            // (ver OrganizadorDashboardController::exportCsv, que ya
            // fusiona ambas filas del CSV en una sola).
            'nombre_curso' => $fila['NombreCurso'] ?? null,
            'id_curso' => $fila['IdCurso'] ?? null,
            // Recategorización visual por edad/género (23/09/2026) —
            // solo informativo, nunca reemplaza `categoria` arriba. Se
            // pisa en cada re-sync igual que género/alerta_numeracion:
            // es un dato calculado siempre por ApiRestEvent, no algo
            // operado localmente.
            'categoria_recalculada' => filled($fila['CategoriaRecalculada'] ?? null) ? $fila['CategoriaRecalculada'] : null,
            'categoria_recalculada_color' => filled($fila['CategoriaRecalculadaColor'] ?? null) ? $fila['CategoriaRecalculadaColor'] : null,
            // Editar datos del participante desde el POS (28/09/2026) —
            // a diferencia de numero_corredor/chip, estas SÍ se pisan en
            // cada re-sync: son solo el id/link para operar, no algo
            // "cargado en el momento" — si el staff ya cambió la
            // categoría vía el popup, el próximo CSV real trae el mismo
            // cambio reflejado desde ApiRestEvent, no hay pisada.
            // Bug real (29/09/2026, UAT: "Data truncated for column
            // categoria_id") — el CSV puede traer un valor no numérico en
            // esta columna si algo cambia del lado de ApiRestEvent (ya
            // corregido ahí, pero esto es defensa en profundidad: la
            // columna es BIGINT, nunca hay que confiarle a ApiRestEvent
            // un valor sin validar antes de escribirlo).
            'categoria_id' => is_numeric($fila['CategoriaId'] ?? null) ? (int) $fila['CategoriaId'] : null,
            'editar_datos_url' => $fila['EditarDatosUrl'] ?? null,
        ]);

        // Numeración de corredor/chip: igual que el estado, solo se toca
        // localmente si todavía está vacía — si el staff ya la cargó a
        // mano en el POS (push-back a ApiRestEvent pendiente o fallido),
        // un re-sync no debe pisarla con un valor vacío. Si el proveedor
        // externo termina cargándola después, sí entra en el próximo sync.
        if (empty($retiro->numero_corredor) && filled($fila['NumeroCorredor'] ?? null)) {
            $retiro->numero_corredor = $fila['NumeroCorredor'];
        }
        if (empty($retiro->chip) && filled($fila['Chip'] ?? null)) {
            $retiro->chip = $fila['Chip'];
        }
        $retiro->actualizar_numeracion_url = $fila['ActualizarNumeracionUrl'] ?? $retiro->actualizar_numeracion_url;

        $retiro->save();
    }

    private function parsearCsv(string $contenido): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contenido);
        rewind($stream);

        $header = fgetcsv($stream);
        $filas = [];
        while (($row = fgetcsv($stream)) !== false) {
            if (count($row) === count($header)) {
                $filas[] = array_combine($header, $row);
            }
        }
        fclose($stream);

        return $filas;
    }
}
