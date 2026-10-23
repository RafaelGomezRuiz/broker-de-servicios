<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Servicio de correo: cada correo pertenece a un sistema (trazabilidad por
 * sistema) y guarda su historial de estados.
 *
 * Los IDs son ULID: no revelan cuántos correos hay ni se pueden adivinar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correo_lotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('sistema_id')->constrained('sistemas');
            $table->string('idempotency_key')->nullable();
            $table->char('idempotency_hash', 64)->nullable();
            $table->unsignedInteger('total');
            $table->timestamps();

            $table->unique(['sistema_id', 'idempotency_key']);
        });

        Schema::create('correos', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('sistema_id')->constrained('sistemas');
            $table->foreignUlid('lote_id')->nullable()->constrained('correo_lotes');
            $table->string('idempotency_key')->nullable();
            // Huella del contenido: la misma llave con otro contenido se rechaza.
            $table->char('idempotency_hash', 64)->nullable();

            $table->string('asunto', 500);
            // Se vacían al cumplirse BROKER_RETENCION_DIAS (contenido_purgado_at).
            $table->longText('cuerpo_html')->nullable();
            $table->longText('cuerpo_texto')->nullable();
            $table->string('solicitado_por')->nullable();

            $table->string('estado', 20);
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->string('error_mensaje', 500)->nullable();
            $table->text('error_detalle')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('contenido_purgado_at')->nullable();
            $table->timestamps();

            $table->unique(['sistema_id', 'idempotency_key']);
            $table->index(['sistema_id', 'created_at']);
            $table->index('estado');
        });

        Schema::create('correo_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('correo_id')->constrained('correos')->cascadeOnDelete();
            $table->string('tipo', 4); // para, cc, cco
            $table->string('direccion', 320);

            $table->index(['correo_id', 'tipo']);
            $table->index('direccion');
        });

        Schema::create('correo_adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('correo_id')->constrained('correos')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('mime');
            $table->unsignedInteger('tamano_bytes');
            $table->char('sha256', 64);
            // Ruta en el disco privado; null cuando el archivo ya se borró.
            $table->string('ruta')->nullable();
            $table->timestamp('eliminado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('correo_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('correo_id')->constrained('correos')->cascadeOnDelete();
            $table->string('estado', 20);
            $table->string('detalle', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_eventos');
        Schema::dropIfExists('correo_adjuntos');
        Schema::dropIfExists('correo_destinatarios');
        Schema::dropIfExists('correos');
        Schema::dropIfExists('correo_lotes');
    }
};
