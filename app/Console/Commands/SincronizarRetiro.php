<?php

namespace App\Console\Commands;

use App\Models\EventoRetiroConfig;
use App\Services\RetiroSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SincronizarRetiro extends Command
{
    protected $signature = 'retiro:sincronizar-todos';

    protected $description = 'Sincroniza el CSV de participantes de todos los eventos configurados para retiro en sitio (para el scheduler, ver routes/console.php)';

    public function handle(RetiroSyncService $service): int
    {
        $configs = EventoRetiroConfig::all();

        if ($configs->isEmpty()) {
            $this->info('No hay eventos configurados para retiro en sitio.');

            return self::SUCCESS;
        }

        foreach ($configs as $config) {
            // Un evento roto no debe frenar el sync de los demás (29/09/2026)
            // — bug real en UAT: `sincronizar()` puede tirar una excepción no
            // atajada (ej. antes del fix de categoria_id) y sin este
            // try/catch el foreach entero se corta ahí, así que el scheduler
            // reportaba el comando completo como "failed with exit code 1"
            // y NINGÚN evento después de ese se llegaba a sincronizar. El
            // servicio ya tiene su propia resiliencia por FILA (ver
            // RetiroSyncService::sincronizar()); esto es la misma idea un
            // nivel más arriba, por EVENTO.
            try {
                $resultado = $service->sincronizar($config);

                if ($resultado['ok']) {
                    $this->info("Evento {$config->evento_id}: {$resultado['actualizados']} participantes sincronizados.");
                } else {
                    $this->error("Evento {$config->evento_id}: {$resultado['error']}");
                }
            } catch (\Throwable $e) {
                Log::error('Sync de retiro en sitio: evento omitido por error', [
                    'evento_id' => $config->evento_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Evento {$config->evento_id}: error inesperado, ver logs ({$e->getMessage()}).");
            }
        }

        return self::SUCCESS;
    }
}
