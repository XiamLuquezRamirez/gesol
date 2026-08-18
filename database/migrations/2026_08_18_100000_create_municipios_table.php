<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guardia idempotente: la tabla ya existe en algunas bases de desarrollo
        // creada fuera del flujo de migraciones. En entornos limpios (CI, SQLite
        // de tests) no existe y se crea normalmente.
        if (! Schema::hasTable('municipios')) {
            Schema::create('municipios', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
