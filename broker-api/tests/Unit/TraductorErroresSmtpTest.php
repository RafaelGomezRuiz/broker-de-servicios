<?php

namespace Tests\Unit;

use App\Services\Correo\TraductorErroresSmtp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Los textos de error son los que devuelven Symfony Mailer y Office 365.
 */
class TraductorErroresSmtpTest extends TestCase
{
    public static function errores(): array
    {
        return [
            'puerto cerrado' => [
                'Connection could not be established with host "smtp.office365.com:587": stream_socket_client(): Unable to connect to smtp.office365.com:587 (No connection could be made because the target machine actively refused it)',
                0, 'conexion', null, 'MAIL_HOST y MAIL_PORT',
            ],
            'host inexistente' => [
                'Connection could not be established with host "smpt.office365.com:587": stream_socket_client(): php_network_getaddresses: getaddrinfo for smpt.office365.com failed: No such host is known.',
                0, 'conexion', null, 'Revisa MAIL_HOST',
            ],
            'sin respuesta' => [
                'Connection could not be established with host "smtp.office365.com:587": stream_socket_client(): Unable to connect (Connection timed out)',
                0, 'conexion', null, 'no respondió a tiempo',
            ],
            'office 365 bloquea el login' => [
                'Failed to authenticate on SMTP server with username "aplicaciones@cultura.gob.do" using the following authenticators: "LOGIN", "XOAUTH2". Authenticator "LOGIN" returned "Expected response code "235" but got code "535", with message "535 5.7.139 Authentication unsuccessful, SmtpClientAuthentication is disabled for the Mailbox.".',
                535, 'autenticacion', 535, '5.7.139',
            ],
            'contraseña incorrecta' => [
                'Failed to authenticate on SMTP server with username "aplicaciones@cultura.gob.do" using the following authenticators: "LOGIN". Authenticator "LOGIN" returned "Expected response code "235" but got code "535", with message "535 5.7.3 Authentication unsuccessful".',
                535, 'autenticacion', 535, 'MAIL_PASSWORD',
            ],
            'sin permiso send as' => [
                'Expected response code "250" but got code "554", with message "554 5.2.252 SendAsDenied; aplicaciones@cultura.gob.do not allowed to send as rrhh@cultura.gob.do".',
                554, 'envio', 554, 'Send As',
            ],
            'error temporal' => [
                'Expected response code "250" but got code "421", with message "421 4.4.2 Connection dropped".',
                421, 'envio', 421, 'Error temporal',
            ],
            'desconocido' => [
                'Algo raro pasó',
                0, 'envio', null, 'no reconocido',
            ],
        ];
    }

    #[DataProvider('errores')]
    public function test_traduce_errores_conocidos(string $texto, int $codigo, string $etapa, ?int $codigoEsperado, string $fragmento): void
    {
        $resultado = (new TraductorErroresSmtp)->traducir(
            new TransportException($texto, $codigo),
            'smtp.office365.com:587',
            'envio',
        );

        $this->assertSame($etapa, $resultado['etapa']);
        $this->assertSame($codigoEsperado, $resultado['codigo']);
        $this->assertStringContainsString($fragmento, $resultado['mensaje']);
    }
}
