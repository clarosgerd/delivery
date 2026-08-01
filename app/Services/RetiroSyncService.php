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
            if (($fila['Estado de pago'] ?? null) !== 'paid') {
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

            $retiro->fill([
                'nombre' => $fila['Nombre'] ?? null,
                'apellido' => $fila['Apellido'] ?? null,
                'categoria' => $fila['Categoría'] ?? null,
                'tipo_formulario' => $fila['Tipo de formulario'] ?? null,
                'talla' => $fila['Talla/Polera'] ?? null,
                'souvenirs' => $fila['Souvenirs'] ?? null,
                'telefono' => $fila['Teléfono'] ?? null,
                'correo' => $fila['Correo'] ?? null,
                'pago_status' => $fila['Estado de pago'] ?? null,
                'referencia' => $fila['Referencia'] ?? null,
            ]);

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
