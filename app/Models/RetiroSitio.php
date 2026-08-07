<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class RetiroSitio extends Model
{
    protected $table = 'retiros_sitio';

    public const ESTADOS = ['pendiente', 'entregado'];

    protected $fillable = [
        'evento_id',
        'documento',
        'nombre',
        'apellido',
        'categoria',
        'tipo_formulario',
        'talla',
        'souvenirs',
        'telefono',
        'correo',
        'pago_status',
        'referencia',
        'estado',
        'entregado_at',
        'entregado_por',
        'numero_corredor',
        'chip',
        'actualizar_numeracion_url',
    ];

    protected function casts(): array
    {
        return [
            'entregado_at' => 'datetime',
        ];
    }

    public function marcarEntregado(?string $entregadoPor): void
    {
        $this->update([
            'estado' => 'entregado',
            'entregado_at' => now(),
            'entregado_por' => $entregadoPor,
        ]);
    }

    public function deshacerEntrega(): void
    {
        $this->update(['estado' => 'pendiente', 'entregado_at' => null, 'entregado_por' => null]);
    }

    /**
     * Carga número de corredor/chip al momento de la entrega en el POS,
     * para cuando el proveedor externo de numeración no llegó a tiempo.
     * Solo asigna lo que todavía esté vacío — si ya tenía un valor cargado
     * (el proveedor sí llegó a tiempo para esta persona), no se toca.
     * Empuja el cambio de vuelta a ApiRestEvent vía `actualizar_numeracion_url`
     * (link firmado entregado en el sync) igual que
     * EnvioDelivery::avanzarEstado() — best-effort, si falla no bloquea la
     * asignación local, solo queda logueado.
     */
    public function asignarNumeracion(?string $numeroCorredor, ?string $chip): void
    {
        $cambios = [];
        if (empty($this->numero_corredor) && filled($numeroCorredor)) {
            $cambios['numero_corredor'] = $numeroCorredor;
        }
        if (empty($this->chip) && filled($chip)) {
            $cambios['chip'] = $chip;
        }
        if (!$cambios) {
            return;
        }

        $this->update($cambios);

        if (! $this->actualizar_numeracion_url) {
            return;
        }

        try {
            $separator = str_contains($this->actualizar_numeracion_url, '?') ? '&' : '?';
            $query = http_build_query($cambios);
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->actualizar_numeracion_url.$separator.$query);

            if (! $response->successful()) {
                Log::warning('Push-back de numeración a ApiRestEvent falló', [
                    'retiro_id' => $this->id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Push-back de numeración a ApiRestEvent lanzó excepción', [
                'retiro_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
