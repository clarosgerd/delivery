<?php

namespace App\Services;

use App\Models\EventoRetiroConfig;
use App\Models\RetiroSitio;
use Illuminate\Support\Facades\Http;

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
            $actualizados++;
        }

        $config->last_synced_at = now();
        $config->save();

        return ['ok' => true, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
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
