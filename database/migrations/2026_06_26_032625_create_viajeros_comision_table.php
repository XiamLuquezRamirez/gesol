<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('viajeros_comision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_viaticos_id')->constrained('solicitudes_viaticos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->string('rol_en_comision')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viajeros_comision');
    }
};
