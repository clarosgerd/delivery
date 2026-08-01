<?php

namespace App\Console\Commands;

use App\Models\EventoRetiroConfig;
use App\Services\RetiroSyncService;
use Illuminate\Console\Command;

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
            $resultado = $service->sincronizar($config);

            if ($resultado['ok']) {
                $this->info("Evento {$config->evento_id}: {$resultado['actualizados']} participantes sincronizados.");
            } else {
                $this->error("Evento {$config->evento_id}: {$resultado['error']}");
            }
        }

        return self::SUCCESS;
    }
}
