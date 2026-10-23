<?php

use App\Models\Sesion;
use Illuminate\Support\Facades\Schedule;

// Estado de la cuenta de correo: si la contraseña expira o Office 365
// bloquea el inicio de sesión, se sabe antes de que fallen los envíos.
Schedule::command('correo:verificar --programada')
    ->hourly()
    ->withoutOverlapping();

// Retención: borra cuerpos y adjuntos de más de BROKER_RETENCION_DIAS.
Schedule::command('correos:purgar')
    ->dailyAt('02:00')
    ->timezone('America/Santo_Domingo')
    ->withoutOverlapping();

// Limpieza de sesiones de la SPA vencidas hace más de 30 días (Sesion::prunable).
Schedule::command('model:prune', ['--model' => [Sesion::class]])
    ->dailyAt('02:30')
    ->timezone('America/Santo_Domingo');
