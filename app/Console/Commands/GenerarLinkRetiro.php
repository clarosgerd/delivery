<?php

namespace App\Console\Commands;

use App\Models\EventoRetiroConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Reporte de entregas en sitio para el organizador (23/09/2026) — calca
 * ApiRestEvent/app/Console/Commands/GenerarLinkDelivery.php.
 */
class GenerarLinkRetiro extends Command
{
    protected $signature = 'retiro:generar-link {evento : ID del evento (evento_id)}';

    protected $description = 'Genera el link firmado (sin expiración) del reporte de entregas en sitio para un evento.';

    public function handle(): int
    {
        $evento = EventoRetiroConfig::where('evento_id', $this->argument('evento'))->first();

        if (! $evento) {
            $this->error('No hay una configuración de retiro en sitio para ese evento_id.');

            return self::FAILURE;
        }

        $this->info("Entregas en sitio de \"{$evento->evento_nombre}\":");
        $this->line('Reporte (HTML, para el organizador): ' . URL::signedRoute('retiro.reporte', ['evento' => $evento->evento_id]));
        $this->line('CSV (descarga puntual): ' . URL::signedRoute('retiro.reporte.csv', ['evento' => $evento->evento_id]));

        return self::SUCCESS;
    }
}
