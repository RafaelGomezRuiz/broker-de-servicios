<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Sistemas que consumen el broker (SGRH, GestionFestibal, ...).
 *
 * Las llaves viven en personal_access_tokens (Sanctum), asociadas a esta
 * tabla. No hay columna de ambiente: cada ambiente tiene su propio broker
 * y su propia base de datos, así que sus llaves ya son distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sistemas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->string('responsable')->nullable();
            $table->timestamp('suspendido_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sistemas');
    }
};
