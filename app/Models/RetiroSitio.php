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
        'genero',
        'fecha_nacimiento',
        'edad_calculada',
        'alerta_numeracion',
        'nombre_curso',
        'id_curso',
        'categoria_recalculada',
        'categoria_recalculada_color',
    ];

    protected function casts(): array
    {
        return [
            'entregado_at' => 'datetime',
            'monto' => 'float',
            'fecha_nacimiento' => 'date',
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

    /**
     * Control de duplicados (24/09/2026) — evita confirmar la entrega de un
     * kit con el mismo `numero_corredor`/`chip` que ya fue entregado
     * FÍSICAMENTE a otro participante del mismo evento (dos personas con el
     * "mismo" kit numerado). Acotado a propósito: solo compara contra otros
     * `retiros_sitio` ya `entregado` — un duplicado entre 2 pendientes no
     * bloquea (puede corregirse antes de entregar cualquiera de los 2, ver
     * decisión del usuario en el plan). No es una unicidad de BD (no hay
     * índice `unique`), es una regla de negocio puntual al momento de
     * entregar.
     */
    public static function conflictoEntregado(int $eventoId, int $exceptId, ?string $numeroCorredor, ?string $chip): ?self
    {
        if (blank($numeroCorredor) && blank($chip)) {
            return null;
        }

        return static::where('evento_id', $eventoId)
            ->where('id', '!=', $exceptId)
            ->where('estado', 'entregado')
            ->where(function ($q) use ($numeroCorredor, $chip) {
                if (filled($numeroCorredor)) {
                    $q->orWhere('numero_corredor', $numeroCorredor);
                }
                if (filled($chip)) {
                    $q->orWhere('chip', $chip);
                }
            })
            ->first();
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
     * para cuando el proveedor externo de numeración no llegó a tiempo, o
     * para corregirlo cuando el aviso de numeración (ver
     * NumeracionRangoChecker en ApiRestEvent) marcó que el que trajo el
     * proveedor no corresponde a su género/edad real (16/09/2026) — antes
     * de esto, un valor ya cargado nunca se tocaba; ahora se pisa cuando el
     * valor que llega es distinto del actual.
     *
     * A diferencia de la primera versión (que solo pegaba a ApiRestEvent
     * si el número/chip cambió), ahora SIEMPRE se consulta
     * `actualizar_numeracion_url` cuando hay algún número vigente (el que
     * ya tenía o el que se acaba de asignar) — para que `alerta_numeracion`
     * quede al día en el momento mismo de "Confirmar entrega", no recién
     * en el próximo sync del CSV completo (pedido explícito del usuario:
     * el aviso tiene que verse en el momento, no una sincronización
     * después). Empuja/consulta vía el link firmado entregado en el sync,
     * igual que EnvioDelivery::avanzarEstado() — best-effort: si falla, no
     * bloquea la asignación/entrega local, solo queda logueado y
     * `alerta_numeracion` conserva el último valor conocido.
     */
    public function asignarNumeracion(?string $numeroCorredor, ?string $chip): void
    {
        $cambios = [];
        if (filled($numeroCorredor) && $numeroCorredor !== $this->numero_corredor) {
            $cambios['numero_corredor'] = $numeroCorredor;
        }
        if (filled($chip) && $chip !== $this->chip) {
            $cambios['chip'] = $chip;
        }
        if ($cambios) {
            $this->update($cambios);
        }

        $numeroVigente = $numeroCorredor ?? $this->numero_corredor;
        if (! $this->actualizar_numeracion_url || empty($numeroVigente)) {
            return;
        }

        try {
            $separator = str_contains($this->actualizar_numeracion_url, '?') ? '&' : '?';
            $query = http_build_query($cambios);
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->actualizar_numeracion_url.($query !== '' ? $separator.$query : ''));

            if ($response->successful()) {
                $alerta = $response->json('alertaNumeracion');
                $this->update(['alerta_numeracion' => filled($alerta) ? $alerta : null]);
            } else {
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
