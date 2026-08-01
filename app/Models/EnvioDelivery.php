<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnvioDelivery extends Model
{
    protected $table = 'envios_delivery';

    public const ESTADOS = ['pendiente', 'confirmado', 'entregado', 'cancelado'];

    protected $fillable = [
        'evento_id',
        'participante_id',
        'repartidor_id',
        'referencia',
        'nombre',
        'direccion',
        'telefono',
        'kit',
        'estado',
        'notas',
        'raw',
        'actualizar_estado_url',
    ];

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(Repartidor::class);
    }

    protected function casts(): array
    {
        return [
            'raw' => 'array',
        ];
    }

    /**
     * Avanza el estado localmente y, si tenemos el `actualizarEstadoUrl`
     * firmado que ApiRestEvent entregó en el import, se lo pega de vuelta
     * para que `participantes.estado_delivery` quede sincronizado allá —
     * sin necesidad de ningún endpoint nuevo en ApiRestEvent, esa URL ya
     * existe (ver DeliveryController::json() en ApiRestEvent). Best-effort:
     * si el push-back falla (evento cerrado, firma vencida, red caída) no
     * bloquea el cambio local, solo queda logueado.
     */
    public function avanzarEstado(string $estado): void
    {
        $this->update(['estado' => $estado]);

        if (! $this->actualizar_estado_url) {
            return;
        }

        try {
            $separator = str_contains($this->actualizar_estado_url, '?') ? '&' : '?';
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->actualizar_estado_url.$separator.'estado='.$estado);

            if (! $response->successful()) {
                Log::warning('Push-back de estado a ApiRestEvent falló', [
                    'envio_id' => $this->id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Push-back de estado a ApiRestEvent lanzó excepción', [
                'envio_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
