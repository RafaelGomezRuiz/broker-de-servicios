<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Personas que entran a la SPA (administradores del broker) y sus sesiones.
 *
 * Reemplaza las tablas por defecto de Laravel (users, password_reset_tokens,
 * sessions): el broker no usa sesiones de servidor ni recuperación de clave
 * por correo. Se borran si existen, por las bases creadas antes de este cambio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->unique();
            $table->string('password');
            $table->timestamp('desactivado_at')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->timestamps();
        });

        /*
         * Una fila por inicio de sesión. El refresh token viaja en una cookie
         * httpOnly y aquí solo se guarda su hash. Se rota en cada uso; el hash
         * anterior se conserva para detectar si alguien reutiliza uno viejo.
         */
        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('token_anterior_hash', 64)->nullable()->index();
            $table->timestamp('rotada_at')->nullable();
            $table->timestamp('ultimo_uso_at');
            // Vida máxima (JWT_REFRESH_TTL): pasada esta hora hay que volver a entrar.
            $table->timestamp('expira_at');
            $table->timestamp('revocada_at')->nullable();
            $table->string('motivo_revocacion', 40)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agente', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
        Schema::dropIfExists('usuarios');
    }
};
