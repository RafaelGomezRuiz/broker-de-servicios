<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Historial de verificaciones de la cuenta de correo (correo:verificar).
 * La última fila es el «estado de la cuenta» que mostrará la SPA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correo_verificaciones', function (Blueprint $table) {
            $table->id();
            $table->boolean('exito');
            // Hasta dónde llegó: conexion, autenticacion, envio o completa.
            $table->string('etapa', 20);
            $table->unsignedSmallInteger('codigo_smtp')->nullable();
            // Explicación en español para quien administra.
            $table->string('mensaje', 500);
            // Respuesta cruda del servidor, para diagnóstico. Nunca incluye la contraseña.
            $table->text('detalle')->nullable();
            $table->string('servidor');
            $table->string('usuario')->nullable();
            $table->string('prueba_enviada_a')->nullable();
            $table->unsignedInteger('duracion_ms');
            // manual (comando) o programada (scheduler).
            $table->string('origen', 20);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_verificaciones');
    }
};
