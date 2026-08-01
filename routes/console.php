<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-sync de los eventos ya configurados (el link/CSV se pega una sola
// vez desde el panel — ver ImportController/RetiroConfigController) para no
// depender de que un admin toque "Sincronizar ahora" a mano. Requiere que
// `php artisan schedule:run` corra cada minuto (cron del SO en producción,
// `schedule:work` en local) — mismo patrón que ya usa ApiRestEvent para sus
// propios crons.
Schedule::command('delivery:sincronizar-todos')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('retiro:sincronizar-todos')->everyFiveMinutes()->withoutOverlapping();
