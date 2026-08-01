<?php

namespace App\Services;

use App\Models\EnvioDelivery;
use App\Models\EventoDeliveryConfig;
use Illuminate\Support\Facades\Http;

/**
 * Lógica de sync de `delivery.dashboard.json`, compartida entre el botón
 * manual ("Sincronizar ahora") y el comando programado
 * (`delivery:sincronizar-todos`, ver routes/console.php) — un solo lugar
 * para no duplicar el mapeo de campos.
 */
class DeliverySyncService
{
    public function sincronizar(EventoDeliveryConfig $config): array
    {
        $response = Http::timeout(15)->get($config->json_url);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'error' => "El link firmado devolvió HTTP {$response->status()} para el evento {$config->evento_id}. Puede haber expirado o estar mal copiado.",
            ];
        }

        $payload = $response->json();
        $items = $payload['participantes'] ?? [];

        if (isset($payload['evento']['nombre']) && ! $config->evento_nombre) {
            $config->evento_nombre = $payload['evento']['nombre'];
        }

        $actualizados = 0;
        $omitidos = [];

        foreach ($items as $item) {
            $participanteId = $item['id'] ?? null;

            if ($participanteId === null) {
                $omitidos[] = $item;
                continue;
            }

            // El estado es operado localmente una vez creado el envío — un
            // re-sync solo trae datos de contacto/kit actualizados, nunca
            // pisa un estado que el equipo de delivery ya avanzó acá (ver
            // EnvioDelivery::avanzarEstado()).
            $envio = EnvioDelivery::firstOrNew([
                'evento_id' => $config->evento_id,
                'participante_id' => $participanteId,
            ]);

            $envio->fill([
                'referencia' => $item['referencia'] ?? null,
                'nombre' => trim(($item['nombre'] ?? '').' '.($item['apellido'] ?? '')) ?: null,
                'direccion' => trim(($item['direccion'] ?? '').($item['ciudad'] ?? '' ? ', '.$item['ciudad'] : '')) ?: null,
                'telefono' => $item['telefono'] ?? null,
                'kit' => $this->formatearKit($item),
                'raw' => $item,
                'actualizar_estado_url' => $item['actualizarEstadoUrl'] ?? null,
            ]);

            if (! $envio->exists) {
                $estadoOrigen = $item['estadoDelivery'] ?? null;
                if (in_array($estadoOrigen, EnvioDelivery::ESTADOS, true)) {
                    $envio->estado = $estadoOrigen;
                }
            }

            $envio->save();

            $actualizados++;
        }

        $config->last_synced_at = now();
        $config->save();

        return ['ok' => true, 'actualizados' => $actualizados, 'omitidos' => $omitidos];
    }

    private function formatearKit(array $item): ?string
    {
        $partes = array_filter([
            $item['categoria'] ?? null,
            $item['talla'] ?? null,
            ! empty($item['souvenirs']) ? implode('+', $item['souvenirs']) : null,
        ]);

        return $partes ? implode(' / ', $partes) : null;
    }
}
