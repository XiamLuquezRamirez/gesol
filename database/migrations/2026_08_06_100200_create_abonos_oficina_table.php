<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos_oficina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->date('fecha_pago');
            $table->string('soporte_path');
            $table->string('soporte_nombre');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->string('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_oficina');
    }
};
