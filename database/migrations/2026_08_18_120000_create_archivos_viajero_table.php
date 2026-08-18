<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('archivos_viajero')) {
            Schema::create('archivos_viajero', function (Blueprint $table) {
                $table->id();
                $table->foreignId('viajero_comision_id')->constrained('viajeros_comision')->cascadeOnDelete();
                $table->enum('tipo', ['comprobante', 'soporte']);
                $table->string('path');
                $table->string('nombre');
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamps();
                $table->index(['viajero_comision_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_viajero');
    }
};
