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
            $table->foreignId('empleado_id')->constrained('empleados');
            $table->string('rol_en_comision')->nullable();
            $table->text('motivo');
            $table->date('fecha_salida');
            $table->string('hora_salida', 5);
            $table->date('fecha_regreso');
            $table->string('hora_regreso', 5);
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
