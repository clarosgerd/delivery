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
        'monto',
        'confirmar_pago_sitio_url',
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
            'monto' => 'float',
        ];
    }

    /**
     * Cobro en sitio (12/08/2026) — ver
     * ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
     * sección 0. `confirmar_pago_sitio_url` solo viene poblado (por el
     * sync) cuando el form_type es sin categoría y sigue pendiente — si el
     * dato local quedó desactualizado (ya se pagó por otro canal desde el
     * último sync), no mostrar el botón igual.
     */
    public function pendienteDeCobroEnSitio(): bool
    {
        return $this->pago_status !== 'paid' && filled($this->confirmar_pago_sitio_url);
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

    /**
     * Cobro en sitio (12/08/2026) — a diferencia de `asignarNumeracion()`,
     * esto **no** es best-effort: si el push-back a ApiRestEvent falla, NO
     * se marca `pago_status=paid` acá ni se debe proceder a entregar el
     * kit — el dinero no quedó registrado como pagado en la fuente de
     * verdad, y esto es lo único de este proyecto que toca dinero. El
     * llamador (`PosController`) decide qué hacer con el `false` (mostrar
     * error, no avanzar a `marcarEntregado()`).
     *
     * Idempotente: si ya estaba `paid` localmente, no vuelve a pegarle a
     * ApiRestEvent.
     */
    public function cobrarPagoSitio(): bool
    {
        if ($this->pago_status === 'paid') {
            return true;
        }

        if (! $this->confirmar_pago_sitio_url) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->confirmar_pago_sitio_url);
        } catch (\Throwable $e) {
            Log::warning('Cobro en sitio: push-back a ApiRestEvent lanzó excepción', [
                'retiro_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful() || ! ($response->json('success') ?? false)) {
            Log::warning('Cobro en sitio: push-back a ApiRestEvent falló', [
                'retiro_id' => $this->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $this->update(['pago_status' => $response->json('pagoStatus') ?? 'paid']);

        return true;
    }
}
