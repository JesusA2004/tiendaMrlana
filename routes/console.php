<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vencimiento de pedidos sin pago (libera stock). Requiere `php artisan schedule:run` cada minuto en el servidor.
Schedule::command('pedidos:vencer')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
