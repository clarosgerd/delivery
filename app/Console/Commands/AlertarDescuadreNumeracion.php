<?php

namespace App\Console\Commands;

use App\Mail\AlertaDescuadreNumeracion;
use App\Models\EventoRetiroConfig;
use App\Services\RetiroSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso diario de descuadres de numeración/chip entre delivery y ApiRestEvent
 * (04/10/2026). Lee lo que dejó el último sync (RetiroSyncService::detectarDescuadre)
 * y, si hay casos, los deja en log y los manda al correo técnico
 * NUMERACION_ALERTA_EMAIL (.env). Sin esa variable solo queda en el log.
 */
class AlertarDescuadreNumeracion extends Command
{
    protected $signature = 'retiro:alertar-descuadre-numeracion';

    protected $description = 'Avisa de participantes con número/chip en delivery que no coinciden con ApiRestEvent (ChronoTrack).';

    public function handle(): int
    {
        $eventos = EventoRetiroConfig::where('usa_numeracion', true)->get();
        $totalCasos = 0;

        foreach ($eventos as $evento) {
            $casos = Cache::get(RetiroSyncService::claveDescuadre($evento->evento_id), []);
            if ($casos === []) {
                continue;
            }

            $totalCasos += count($casos);
            $texto = $this->armarTexto($evento, $casos);

            Log::error('Descuadre de numeración entre delivery y ApiRestEvent', [
                'evento_id' => $evento->evento_id,
                'casos' => count($casos),
            ]);

            $destinos = config('services.numeracion.alerta_emails', []);
            if ($destinos !== []) {
                Mail::to($destinos)->send(new AlertaDescuadreNumeracion($evento->evento_id, $texto, count($casos)));
            } else {
                $this->warn('NUMERACION_ALERTA_EMAILS no está configurada: el aviso solo quedó en el log.');
            }

            $this->line($texto);
        }

        $this->info("Casos con descuadre: {$totalCasos}.");

        return self::SUCCESS;
    }

    private function armarTexto(EventoRetiroConfig $evento, array $casos): string
    {
        $lineas = [
            'Evento '.$evento->evento_id.' ('.$evento->evento_nombre.'): '.count($casos).' participante(s) con descuadre entre delivery y ApiRestEvent.',
            'Lo que va a ChronoTrack es lo que tiene ApiRestEvent.',
            '',
        ];

        foreach ($casos as $caso) {
            $lineas[] = sprintf(
                '- %s %s (%s) | número delivery=%s api=%s | chip delivery=%s api=%s | %s',
                $caso['nombre'] ?? '', $caso['apellido'] ?? '', $caso['documento'],
                $caso['numero_delivery'] ?: '-', $caso['numero_api'] ?: '-',
                $caso['chip_delivery'] ?: '-', $caso['chip_api'] ?: '-',
                implode(', ', $caso['problemas'])
            );
        }

        return implode("\n", $lineas);
    }
}
