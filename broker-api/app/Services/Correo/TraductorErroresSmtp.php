<?php

namespace App\Services\Correo;

use App\Models\CorreoVerificacion;
use Throwable;

/**
 * Convierte los errores del servidor SMTP (sobre todo Office 365) en un
 * mensaje en español que diga qué revisar.
 *
 * Las reglas van de la más específica a la más general: la primera que
 * coincide gana.
 */
class TraductorErroresSmtp
{
    /**
     * @param  string  $etapaPorDefecto  Dónde ocurrió: conexión (al iniciar sesión) o envío.
     * @return array{etapa: string, codigo: int|null, mensaje: string}
     */
    public function traducir(Throwable $error, string $servidor, string $etapaPorDefecto): array
    {
        $texto = $error->getMessage();
        $codigo = $this->codigoSmtp($error);

        foreach ($this->reglas($servidor) as [$patron, $etapa, $mensaje]) {
            if (preg_match($patron, $texto)) {
                return ['etapa' => $etapa, 'codigo' => $codigo, 'mensaje' => $mensaje];
            }
        }

        if ($codigo !== null && $codigo >= 400 && $codigo < 500) {
            return [
                'etapa' => $etapaPorDefecto,
                'codigo' => $codigo,
                'mensaje' => "Error temporal del servidor ({$codigo}). Vuelve a intentarlo en unos minutos.",
            ];
        }

        return [
            'etapa' => $etapaPorDefecto,
            'codigo' => $codigo,
            'mensaje' => 'Error no reconocido del servidor de correo. Revisa el detalle técnico.',
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string}> */
    private function reglas(string $servidor): array
    {
        $conexion = CorreoVerificacion::ETAPA_CONEXION;
        $autenticacion = CorreoVerificacion::ETAPA_AUTENTICACION;
        $envio = CorreoVerificacion::ETAPA_ENVIO;

        return [
            // --- Conexión ---
            ['/getaddrinfo|No such host|name or service not known/i', $conexion,
                "No se encontró el servidor {$servidor}. Revisa MAIL_HOST."],
            ['/timed out|timeout/i', $conexion,
                "El servidor {$servidor} no respondió a tiempo. Revisa MAIL_PORT, el firewall o la salida a internet del clúster."],
            ['/Connection could not be established|Connection refused|actively refused/i', $conexion,
                "No se pudo conectar a {$servidor}. Revisa MAIL_HOST y MAIL_PORT."],
            ['/STARTTLS|SSL|certificate|crypto/i', $conexion,
                "Falló la conexión segura (TLS) con {$servidor}. Para Office 365 usa el puerto 587."],

            // --- Autenticación ---
            ['/5\.7\.139/', $autenticacion,
                'Office 365 rechazó el inicio de sesión (5.7.139): contraseña incorrecta, SMTP AUTH deshabilitado para la cuenta, o bloqueado por MFA / acceso condicional.'],
            ['/Failed to find an authenticator/i', $autenticacion,
                'El servidor no ofrece un método de autenticación compatible. Revisa el puerto (587 con STARTTLS para Office 365).'],
            ['/5\.7\.3\b|\b535\b|Failed to authenticate/i', $autenticacion,
                'Usuario o contraseña incorrectos. Revisa MAIL_USERNAME y MAIL_PASSWORD.'],

            // --- Envío ---
            ['/5\.7\.60|SendAsDenied/i', $envio,
                'La cuenta MAIL_USERNAME no tiene permiso para enviar como MAIL_FROM_ADDRESS (permiso «Send As» en Exchange).'],
            ['/5\.7\.57|\b530\b|not authenticated/i', $envio,
                'El servidor exige autenticación para enviar. Configura MAIL_USERNAME y MAIL_PASSWORD.'],
            ['/5\.1\.\d|\b550\b|\b553\b|recipient/i', $envio,
                'El servidor rechazó el destinatario. Revisa la dirección indicada.'],
        ];
    }

    /** Código SMTP de 3 dígitos, si se puede deducir del error. */
    private function codigoSmtp(Throwable $error): ?int
    {
        $codigo = (int) $error->getCode();
        if ($codigo >= 200 && $codigo < 600) {
            return $codigo;
        }

        if (preg_match('/got code "(\d{3})"|\b([245]\d\d)[ -][245]\.\d{1,3}\.\d{1,3}/', $error->getMessage(), $m)) {
            return (int) ($m[1] ?: $m[2]);
        }

        return null;
    }
}
