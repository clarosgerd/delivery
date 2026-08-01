<?php

namespace App\Console\Commands;

use App\Models\EventoDeliveryConfig;
use App\Services\DeliverySyncService;
use Illuminate\Console\Command;

class SincronizarDelivery extends Command
{
    protected $signature = 'delivery:sincronizar-todos';

    protected $description = 'Sincroniza delivery.dashboard.json de todos los eventos configurados (para el scheduler, ver routes/console.php)';

    public function handle(DeliverySyncService $service): int
    {
        $configs = EventoDeliveryConfig::all();

        if ($configs->isEmpty()) {
            $this->info('No hay eventos configurados para delivery.');

            return self::SUCCESS;
        }

        foreach ($configs as $config) {
            $resultado = $service->sincronizar($config);

            if ($resultado['ok']) {
                $this->info("Evento {$config->evento_id}: {$resultado['actualizados']} envíos sincronizados.");
            } else {
                $this->error("Evento {$config->evento_id}: {$resultado['error']}");
            }
        }

        return self::SUCCESS;
    }
}
