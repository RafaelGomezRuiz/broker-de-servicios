<?php

namespace App\Console\Commands;

use App\Models\CorreoVerificacion;
use App\Services\Correo\VerificadorSmtp;
use Illuminate\Console\Command;

class CorreoVerificar extends Command
{
    protected $signature = 'correo:verificar
        {--enviar-prueba= : Además envía un correo real a esta dirección}
        {--programada : Marca la verificación como automática (la usa el scheduler)}';

    protected $description = 'Comprueba que la cuenta de correo (MAIL_*) puede conectarse e iniciar sesión';

    /** Días de historial que se conservan. */
    private const DIAS_HISTORIAL = 30;

    public function handle(VerificadorSmtp $verificador): int
    {
        $destino = $this->option('enviar-prueba');
        if ($destino !== null && ! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $this->components->error("«{$destino}» no es un correo válido.");

            return self::FAILURE;
        }

        $config = config('mail.mailers.smtp');
        $this->components->twoColumnDetail('Servidor', $config['host'].':'.$config['port']);
        $this->components->twoColumnDetail('Usuario', $config['username'] ?: '(sin autenticación)');
        $this->components->twoColumnDetail('Remitente', config('mail.from.address'));
        if ($destino) {
            $this->components->twoColumnDetail('Correo de prueba a', $destino);
        }
        $this->newLine();

        $resultado = $verificador->verificar($destino, $this->option('programada') ? 'programada' : 'manual');

        CorreoVerificacion::where('created_at', '<', now()->subDays(self::DIAS_HISTORIAL))->delete();

        if ($resultado->exito) {
            // El componente ya agrega el punto final.
            $this->components->info(rtrim($resultado->mensaje, '.')." ({$resultado->duracion_ms} ms)");

            return self::SUCCESS;
        }

        $this->components->error("Falló en la etapa «{$resultado->etapa}»: {$resultado->mensaje}");
        $this->line('  <fg=gray>Detalle técnico:</> '.$resultado->detalle);
        $this->newLine();

        return self::FAILURE;
    }
}
